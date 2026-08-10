<?php
namespace App\Observers;

use App\Models\PortfolioProject;
use App\Services\ImageService;
use App\Support\HomepageCache;

class PortfolioProjectObserver
{
    public function __construct(
        private ImageService $imageService
    ) {}

    public function saved(PortfolioProject $project): void
    {
        HomepageCache::forgetSection('projects');

        if ($project->wasChanged('cover_image') && $project->cover_image) {
            $webpPath = $this->imageService->convertToWebP(
                path: $project->cover_image,
                disk: 'public',
                quality: 85,
                maxWidth: 1200
            );

            if ($webpPath !== $project->cover_image) {
                $project->updateQuietly(['cover_image' => $webpPath]);
            }
        }
    }

    public function deleted(PortfolioProject $project): void
    {
        HomepageCache::forgetSection('projects');
    }
}
