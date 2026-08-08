<?php

namespace App\Http\Controllers;

use App\Models\EmailMessage;
use Resend\Contracts\Client as ResendClient;

class EmailAttachmentController extends Controller
{
    public function __invoke(
        EmailMessage $message,
        string $attachmentId,
        ResendClient $resend
    ) {
        abort_unless(
            auth()->user()?->hasRole([
                'Super Admin',
                'Admin',
                'Support',
            ]),
            403,
            'Not authorized to download email attachments.'
        );

        $attachment = collect($message->attachments ?? [])
            ->firstWhere('id', $attachmentId);

        abort_unless(
            $attachment,
            404,
            'Attachment metadata not found.'
        );

        $resendEmailId = data_get(
            $message->metadata,
            'resend_email_id'
        );

        abort_unless(
            $resendEmailId,
            404,
            'Resend email ID not found.'
        );

        $remoteAttachment = $resend
            ->emails
            ->receiving
            ->attachments
            ->get(
                $resendEmailId,
                $attachmentId
            );

        abort_unless(
            ! empty($remoteAttachment->download_url),
            404,
            'Resend download URL not found.'
        );

        return redirect()->away(
            $remoteAttachment->download_url
        );
    }
}