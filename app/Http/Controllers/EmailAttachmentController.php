<?php

namespace App\Http\Controllers;

use App\Models\EmailMessage;
use App\Services\Mail\AttachmentPolicy;
use App\Support\AdminAccess;
use Illuminate\Support\Facades\Storage;
use Resend\Contracts\Client as ResendClient;

class EmailAttachmentController extends Controller
{
    public function __invoke(
        EmailMessage $message,
        string $attachmentId,
        AttachmentPolicy $attachmentPolicy
    ) {
        abort_unless(AdminAccess::canAccessPanel() && AdminAccess::canManageInbox(), 403);

        if ($message->direction === 'outbound') {
            abort_unless(AdminAccess::canComposeEmail(), 403);

            // Outbound metadata uses private file paths, not Resend attachment IDs.
            $attachment = collect($message->attachments ?? [])
                ->first(fn ($item) => is_array($item)
                    && isset($item['path'])
                    && hash_equals(hash('sha256', (string) $item['path']), $attachmentId));
            abort_unless($attachment, 404);
            $this->assertAllowed($message, $attachment, $attachmentPolicy);

            $path = $attachment['path'];
            abort_unless(is_string($path)
                && str_starts_with($path, 'email-outgoing/')
                && ! str_contains($path, '..')
                && Storage::disk('local')->exists($path), 404);
            abort_unless(Storage::disk('local')->size($path) === (int) $attachment['size'], 403);

            return Storage::disk('local')->download($path, basename((string) $attachment['filename']));
        }

        abort_unless($message->direction === 'inbound', 404);
        $attachment = collect($message->attachments ?? [])->firstWhere('id', $attachmentId);
        abort_unless($attachment, 404);
        $this->assertAllowed($message, $attachment, $attachmentPolicy);

        $resendEmailId = data_get($message->metadata, 'resend_email_id');
        abort_unless(is_string($resendEmailId) && $resendEmailId !== '', 404);

        // Resolve the remote client only for an authorized inbound attachment.
        $resend = app(ResendClient::class);
        $remoteAttachment = $resend->emails->receiving->attachments->get($resendEmailId, $attachmentId);
        $downloadUrl = (string) $remoteAttachment->download_url;
        $urlParts = parse_url($downloadUrl);
        abort_unless(is_array($urlParts)
            && ($urlParts['scheme'] ?? '') === 'https'
            && ! empty($urlParts['host'])
            && ! isset($urlParts['user'])
            && ! isset($urlParts['pass']), 502);

        return redirect()->away($downloadUrl);
    }

    private function assertAllowed(EmailMessage $message, array $attachment, AttachmentPolicy $policy): void
    {
        $batch = $policy->evaluateBatchLimits($message->attachments ?? []);
        abort_unless($batch['allowed'], 403, $batch['reason'] ?? 'Attachment blocked.');
        $item = $policy->evaluateAttachment($attachment);
        abort_unless($item['allowed'], 403, $item['reason'] ?? 'Attachment blocked.');
    }
}
