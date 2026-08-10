<?php

namespace App\Console\Commands;

use App\Models\DigitalProductVersion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MigrateDigitalProductFilesPrivate extends Command
{
    protected $signature = 'digital-products:migrate-private
        {--dry-run : Inspect files without copying or deleting}
        {--delete-public : Delete legacy public files after a verified private copy}';

    protected $description = 'Copy legacy public digital product files to private local storage';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deletePublic = (bool) $this->option('delete-public');
        $counts = [
            'migrated' => 0,
            'already_private' => 0,
            'missing' => 0,
            'errors' => 0,
        ];

        DigitalProductVersion::query()
            ->select(['id', 'file_path'])
            ->orderBy('id')
            ->each(function (DigitalProductVersion $version) use ($dryRun, $deletePublic, &$counts): void {
                $path = $version->file_path;

                if (! $this->isSafeDigitalProductPath($path)) {
                    $counts['errors']++;
                    $this->warn("Skipped suspicious path for version #{$version->id}: {$path}");

                    return;
                }

                $localExists = Storage::disk('local')->exists($path);
                $publicExists = Storage::disk('public')->exists($path);

                if ($localExists) {
                    $counts['already_private']++;

                    if ($publicExists) {
                        try {
                            $filesMatch = $this->filesMatch($path);
                        } catch (Throwable $exception) {
                            $counts['errors']++;
                            $this->error("Failed verifying version #{$version->id}: {$exception->getMessage()}");

                            return;
                        }

                        if (! $filesMatch) {
                            $counts['errors']++;
                            $this->error("Conflict for version #{$version->id}: private and public files differ.");

                            return;
                        }

                        if ($deletePublic && ! $dryRun) {
                            Storage::disk('public')->delete($path);
                        }
                    }

                    return;
                }

                if (! $publicExists) {
                    $counts['missing']++;
                    $this->warn("Missing file for version #{$version->id}: {$path}");

                    return;
                }

                if ($dryRun) {
                    $counts['migrated']++;
                    $this->line("Would copy version #{$version->id}: {$path}");

                    return;
                }

                try {
                    $source = Storage::disk('public')->readStream($path);

                    if ($source === false) {
                        throw new \RuntimeException('Unable to open public source stream.');
                    }

                    try {
                        Storage::disk('local')->put($path, $source);
                    } finally {
                        if (is_resource($source)) {
                            fclose($source);
                        }
                    }

                    if (! Storage::disk('local')->exists($path)) {
                        throw new \RuntimeException('Private destination was not written.');
                    }

                    if (! $this->filesMatch($path)) {
                        throw new \RuntimeException('Private destination failed integrity verification.');
                    }

                    if ($deletePublic) {
                        Storage::disk('public')->delete($path);
                    }

                    $counts['migrated']++;
                } catch (Throwable $exception) {
                    $counts['errors']++;
                    $this->error("Failed migrating version #{$version->id}: {$exception->getMessage()}");
                }
            });

        foreach ($counts as $key => $count) {
            $this->info("{$key}: {$count}");
        }

        if ($dryRun) {
            $this->warn('Dry run: no files were copied or deleted.');
        }

        return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    public function isSafeDigitalProductPath(?string $path): bool
    {
        if (! is_string($path) || $path === '') {
            return false;
        }

        if (str_contains($path, "\0") || str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path)) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);

        if (str_contains($normalized, '../') || str_starts_with($normalized, '../') || str_contains($normalized, '/..')) {
            return false;
        }

        return str_starts_with($normalized, 'digital-products/')
            || str_starts_with($normalized, 'products/files/');
    }

    private function filesMatch(string $path): bool
    {
        $local = Storage::disk('local');
        $public = Storage::disk('public');

        if ($local->size($path) !== $public->size($path)) {
            return false;
        }

        return $this->hashFile('local', $path) === $this->hashFile('public', $path);
    }

    private function hashFile(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);

        if ($stream === false) {
            throw new \RuntimeException("Unable to open {$disk} stream.");
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $stream);

        if (is_resource($stream)) {
            fclose($stream);
        }

        return hash_final($context);
    }
}
