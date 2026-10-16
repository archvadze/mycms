<?php
namespace App\Services\Mail;

use App\Mail\OutgoingEmail;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Services\MailSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class EmailSendingService
{
    public function __construct(private readonly MailSettings $mailSettings) {}

    // Backwards compatible with the existing EmailSendingService tests.
    public function send(string $to, string $subject, string $body): EmailMessage
    {
        return $this->sendMessage([$to], [], [], $subject, $body);
    }

    public function saveDraft(array $to, array $cc, array $bcc, string $subject, string $html, array $files = [], ?EmailMessage $draft = null): EmailMessage
    {
        $to = $this->addresses($to, false);
        $cc = $this->addresses($cc, false);
        $bcc = $this->addresses($bcc, false);
        $this->validateFiles($files);
        if ($draft && ($draft->direction !== 'outbound' || $draft->status !== 'draft')) {
            throw new RuntimeException('Only drafts can be updated.');
        }
        $attributes = [
            'direction' => 'outbound', 'status' => 'draft', 'source' => config('mail.default'),
            'from_name' => $this->mailSettings->senderName(), 'from_email' => $this->mailSettings->senderEmail(),
            'to_email' => $to[0] ?? '', 'recipients' => $to, 'cc_recipients' => $cc, 'bcc_recipients' => $bcc,
            'subject' => trim($subject), 'html_body' => $html,
            'text_body' => trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br />'], "\n", $html)))),
            'attachments' => $files, 'draft_saved_at' => now(), 'is_read' => true,
        ];
        return DB::transaction(function () use ($draft, $attributes): EmailMessage {
            if ($draft) { $draft->update($attributes); return $draft->refresh(); }
            $thread = EmailThread::create(['subject' => $attributes['subject'] ?: '(Draft)', 'status' => 'open', 'last_message_at' => now()]);
            return EmailMessage::create($attributes + ['email_thread_id' => $thread->id, 'metadata' => [], 'send_attempts' => 0]);
        });
    }

    public function sendMessage(array $to, array $cc, array $bcc, string $subject, string $html, array $files = [], ?EmailMessage $draft = null): EmailMessage
    {
        if (! $this->mailSettings->enabled()) { throw new RuntimeException('Email sending is disabled.'); }
        $to = $this->addresses($to, true);
        $cc = $this->addresses($cc, false);
        $bcc = $this->addresses($bcc, false);
        $all = array_merge($to, $cc, $bcc);
        if (count($all) !== count(array_unique(array_map('strtolower', $all)))) { throw new RuntimeException('Duplicate recipient across To, CC and BCC.'); }
        $subject = trim($subject);
        $text = trim(html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br />'], "\n", $html))));
        if ($subject === '' || $text === '') { throw new RuntimeException('Subject and message are required.'); }
        $this->validateFiles($files);
        if ($draft && ($draft->direction !== 'outbound' || $draft->status !== 'draft')) { throw new RuntimeException('Draft has already been sent.'); }
        $attrs = [
            'direction' => 'outbound', 'source' => config('mail.default'), 'status' => 'pending',
            'from_name' => $this->mailSettings->senderName(), 'from_email' => $this->mailSettings->senderEmail(),
            'to_email' => $to[0], 'recipients' => $to, 'cc_recipients' => $cc, 'bcc_recipients' => $bcc,
            'subject' => $subject, 'text_body' => $text, 'html_body' => $html, 'attachments' => $files,
            'is_read' => true, 'send_attempts' => 0, 'sending_token' => (string) Str::uuid(),
        ];
        $message = DB::transaction(function () use ($attrs, $draft): EmailMessage {
            if ($draft) { $draft->update($attrs); return $draft->refresh(); }
            $thread = EmailThread::create(['subject' => $attrs['subject'], 'status' => 'open', 'last_message_at' => now()]);
            return EmailMessage::create($attrs + ['email_thread_id' => $thread->id, 'metadata' => []]);
        });
        $message->update(['status' => 'sending', 'sending_started_at' => now(), 'send_attempts' => 1]);
        try {
            $mail = (new OutgoingEmail($subject, $text, $html, $files))
                ->from($this->mailSettings->senderEmail(), $this->mailSettings->senderName())
                ->to($to);
            if ($cc) { $mail->cc($cc); }
            if ($bcc) { $mail->bcc($bcc); }
            $sent = Mail::send($mail);
            $message->update(['status' => 'sent', 'message_id' => $sent?->getMessageId(), 'sent_at' => now(), 'last_send_error' => null]);
        } catch (Throwable $e) {
            try { $message->update(['status' => 'unknown', 'last_send_error' => mb_substr($e->getMessage(), 0, 5000)]); }
            catch (Throwable $storageError) { Log::critical('Email status persistence failed', ['email_message_id' => $message->id, 'exception' => get_class($storageError)]); }
            Log::error('Outgoing email delivery uncertain', ['email_message_id' => $message->id, 'exception' => get_class($e)]);
            throw new RuntimeException('Email delivery could not be confirmed. Check the message status before retrying.', 0, $e);
        }
        return $message->refresh();
    }

    private function addresses(array $values, bool $required): array
    {
        $addresses = [];
        foreach ($values as $value) {
            $value = trim((string) $value);
            if ($value === '') { continue; }
            if (!filter_var($value, FILTER_VALIDATE_EMAIL) || mb_strlen($value) > 254) { throw new RuntimeException('Invalid recipient email address.'); }
            $addresses[] = $value;
        }
        if ($required && ! $addresses) { throw new RuntimeException('At least one To recipient is required.'); }
        if (count($addresses) > 50) { throw new RuntimeException('Maximum 50 addresses per field.'); }
        return $addresses;
    }

    private function validateFiles(array $files): void
    {
        $policy = app(AttachmentPolicy::class);
        $result = $policy->evaluate($files);
        if (! $result['allowed']) { throw new RuntimeException($result['reason']); }
        foreach ($files as $file) {
            $path = $file['path'] ?? '';
            if (! is_string($path) || !str_starts_with($path, 'email-outgoing/') || !Storage::disk('local')->exists($path)) {
                throw new RuntimeException('Attachment is not available in private storage.');
            }
            if (Storage::disk('local')->size($path) !== (int) $file['size']) { throw new RuntimeException('Attachment size mismatch.'); }
        }
    }
}
