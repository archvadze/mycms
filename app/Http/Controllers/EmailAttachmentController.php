<?php

namespace App\Http\Controllers;

use App\Models\EmailMessage;
use App\Services\Mail\AttachmentPolicy;
use Resend\Contracts\Client as ResendClient;

class EmailAttachmentController extends Controller
{
    public function __invoke(
        EmailMessage $message,
        string $attachmentId,
        ResendClient $resend,
        AttachmentPolicy $attachmentPolicy
    ) {
        abort_unless(
            auth()->user()?->hasRole([
                'Super Admin',
                'Admin',
                'Support',
            ]),
            403
        );

        $attachment = collect($message->attachments ?? [])
            ->firstWhere('id', $attachmentId);

        abort_unless($attachment, 404);

        $batchResult = $attachmentPolicy
            ->evaluateBatchLimits($message->attachments ?? []);

        abort_unless(
            $batchResult['allowed'],
            403,
            $batchResult['reason'] ?? 'Attachments blocked.'
        );

        $policyResult = $attachmentPolicy
            ->evaluateAttachment($attachment);

        abort_unless(
            $policyResult['allowed'],
            403,
            $policyResult['reason'] ?? 'Attachment blocked.'
        );

        $resendEmailId = data_get(
            $message->metadata,
            'resend_email_id'
        );

        abort_unless($resendEmailId, 404);

        $remoteAttachment = $resend
            ->emails
            ->receiving
            ->attachments
            ->get(
                $resendEmailId,
                $attachmentId
            );

        $downloadUrl = (string) $remoteAttachment->download_url;

        abort_if($downloadUrl === '', 404);

        return redirect()->away($downloadUrl);
    }
}