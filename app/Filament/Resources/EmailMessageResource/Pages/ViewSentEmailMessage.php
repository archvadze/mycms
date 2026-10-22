<?php

namespace App\Filament\Resources\EmailMessageResource\Pages;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Services\Mail\AttachmentPolicy;
use App\Support\AdminAccess;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Facades\Storage;

class ViewSentEmailMessage extends Page
{
    protected static string $resource = EmailMessageResource::class;

    protected static ?string $title = 'Outgoing message';

    protected string $view = 'filament.resources.email-message-resource.pages.view-sent-email';

    public EmailMessage $message;

    public static function canAccess(array $parameters = []): bool
    {
        return AdminAccess::canComposeEmail();
    }

    public function mount(int|string $record): void
    {
        abort_unless(static::canAccess() && AdminAccess::canAccessPanel(), 403);

        $this->message = EmailMessage::query()
            ->where('direction', 'outbound')
            ->where('status', '!=', 'draft')
            ->findOrFail($record);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('forward')
                ->label('Forward')
                ->icon('heroicon-o-arrow-uturn-right')
                ->authorize(fn (): bool => AdminAccess::canComposeEmail())
                ->url(fn (): string => EmailMessageResource::getUrl('compose', ['forward' => $this->message->getKey()])),
            Action::make('backToSent')
                ->label('Back to Sent')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(EmailMessageResource::getUrl('sent')),
        ];
    }

    /** @return array<int, array{filename: string, size: string, download_url: ?string}> */
    public function attachmentItems(): array
    {
        $attachments = $this->message->attachments ?? [];
        if (! is_array($attachments)) {
            return [];
        }

        $policy = app(AttachmentPolicy::class);
        $batch = $policy->evaluateBatchLimits($attachments);
        $items = [];

        foreach ($attachments as $attachment) {
            if (! is_array($attachment)) {
                continue;
            }

            $path = $attachment['path'] ?? null;
            $allowed = $batch['allowed']
                && $policy->evaluateAttachment($attachment)['allowed']
                && is_string($path)
                && str_starts_with($path, 'email-outgoing/')
                && ! str_contains($path, '..')
                && Storage::disk('local')->exists($path)
                && Storage::disk('local')->size($path) === (int) ($attachment['size'] ?? 0);

            $items[] = [
                'filename' => (string) ($attachment['filename'] ?? 'Attachment'),
                'size' => number_format(((int) ($attachment['size'] ?? 0)) / 1024, 1).' KB',
                'download_url' => $allowed ? route('email-messages.attachments.download', [
                    'message' => $this->message->getKey(),
                    'attachmentId' => hash('sha256', $path),
                ]) : null,
            ];
        }

        return $items;
    }
}
