<?php

namespace App\Listeners;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Resend\Contracts\Client as ResendClient;
use Resend\Laravel\Events\EmailReceived;
use Throwable;

class HandleReceivedEmail
{
    public function __construct(
        private ResendClient $resend
    ) {}

    public function handle(EmailReceived $event): void
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];

        $emailId = $data['email_id'] ?? null;

        if (! $emailId) {
            Log::warning('Resend inbound email missing email_id');

            return;
        }

        try {
            $received = $this->retrieveReceivedEmail($emailId);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve inbound email from Resend', [
                'email_id' => $emailId,
                'error' => $e->getMessage(),
            ]);

            return;
        }

        $messageId = $received->message_id ?? $data['message_id'] ?? $emailId;

        if (EmailMessage::where('message_id', $messageId)->exists()) {
            return;
        }

        [$fromName, $fromEmail] = $this->parseAddress(
            $received->from ?? ($data['from'] ?? '')
        );

        $to = $received->to ?? ($data['to'] ?? []);

        $toEmail = is_array($to)
            ? ($to[0] ?? null)
            : $to;

        $subject = $received->subject
            ?? $data['subject']
            ?? '(No subject)';

        $attachments = $received->attachments ?? [];

        DB::transaction(function () use (
            $payload,
            $received,
            $emailId,
            $messageId,
            $fromName,
            $fromEmail,
            $toEmail,
            $subject,
            $attachments
        ) {
            $thread = EmailThread::create([
                'subject' => $subject,
                'status' => 'open',
                'last_message_at' => $received->created_at ?? now(),
            ]);

            EmailMessage::create([
                'email_thread_id' => $thread->id,
                'direction' => 'inbound',
                'source' => 'resend',
                'message_id' => $messageId,
                'from_name' => $fromName,
                'from_email' => $fromEmail ?: 'unknown@example.invalid',
                'to_email' => $toEmail ?: config('agency.admin_email'),
                'subject' => $subject,
                'text_body' => $received->text ?? null,
                'html_body' => $received->html ?? null,
                'attachments' => $attachments,
                'metadata' => [
                    'resend_email_id' => $emailId,
                    'headers' => $received->headers ?? null,
                    'reply_to' => $received->reply_to ?? null,
                    'cc' => $received->cc ?? null,
                    'bcc' => $received->bcc ?? null,
                    'webhook' => $payload,
                ],
                'is_read' => false,
                'received_at' => $received->created_at ?? now(),
            ]);
        });
    }

    protected function retrieveReceivedEmail(string $emailId): object
    {
        return $this->resend->emails->receiving->get($emailId);
    }

    private function parseAddress(string $address): array
    {
        if (preg_match('/^(.*?)\s*<([^>]+)>$/', $address, $matches)) {
            return [
                trim($matches[1], " \t\n\r\0\x0B\"'"),
                trim($matches[2]),
            ];
        }

        return [null, trim($address)];
    }
}
