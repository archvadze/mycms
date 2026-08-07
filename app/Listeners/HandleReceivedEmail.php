<?php

namespace App\Listeners;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Resend\Laravel\Events\EmailReceived;

class HandleReceivedEmail
{
    public function handle(EmailReceived $event): void
    {
        $payload = $event->payload;
        $data = $payload['data'] ?? [];

        $emailId = $data['email_id'] ?? null;
        $messageId = $data['message_id'] ?? null;

        if (! $emailId) {
            Log::warning('Resend inbound email missing email_id', [
                'payload' => $payload,
            ]);

            return;
        }

        $uniqueMessageId = $messageId ?: $emailId;

        if (EmailMessage::where('message_id', $uniqueMessageId)->exists()) {
            return;
        }

        $from = $data['from'] ?? '';
        $to = $data['to'] ?? [];

        [$fromName, $fromEmail] = $this->parseAddress($from);

        $toEmail = is_array($to)
            ? ($to[0] ?? null)
            : $to;

        $subject = $data['subject'] ?? '(No subject)';

        DB::transaction(function () use (
            $payload,
            $data,
            $emailId,
            $uniqueMessageId,
            $fromName,
            $fromEmail,
            $toEmail,
            $subject
        ) {
            $thread = EmailThread::create([
                'subject' => $subject,
                'status' => 'open',
                'last_message_at' => $data['created_at'] ?? now(),
            ]);

            EmailMessage::create([
                'email_thread_id' => $thread->id,
                'direction' => 'inbound',
                'source' => 'resend',
                'message_id' => $uniqueMessageId,
                'from_name' => $fromName,
                'from_email' => $fromEmail ?: 'unknown@example.invalid',
                'to_email' => $toEmail ?: config('agency.admin_email'),
                'subject' => $subject,
                'attachments' => $data['attachments'] ?? [],
                'metadata' => [
                    'resend_email_id' => $emailId,
                    'webhook' => $payload,
                ],
                'is_read' => false,
                'received_at' => $data['created_at'] ?? now(),
            ]);
        });
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
