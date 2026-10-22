<?php

namespace App\Services\Mail;

use App\Models\EmailMessage;

final class ForwardEmailPrefill
{
    /** @return array{subject: string, body: string} */
    public static function fromMessage(EmailMessage $message): array
    {
        $subject = trim((string) $message->subject);
        $subject = $subject !== '' ? $subject : '(No subject)';
        if (! preg_match('/^fwd?:/i', $subject)) {
            $subject = 'Fwd: ' . $subject;
        }
        $subject = mb_substr($subject, 0, 255);

        // Never trust stored HTML in forwarded messages: preserve readable text only.
        $text = (string) ($message->text_body ?? '');
        if (trim($text) === '') {
            $html = preg_replace('/<\s*br\s*\/?>|<\/\s*(?:p|div|li)\s*>/i', "\n", (string) ($message->html_body ?? ''));
            $text = html_entity_decode(strip_tags($html ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $text = trim(str_replace(["\r\n", "\r"], "\n", $text));

        $sender = trim((string) ($message->from_email ?? ''));
        $originalSubject = trim((string) ($message->subject ?? ''));
        $lines = [
            '',
            '---------- Forwarded message ----------',
            'From: ' . $sender,
            'Subject: ' . $originalSubject,
            '',
            $text,
        ];

        // RichEditor takes HTML. Encode every piece of untrusted content.
        $paragraphs = array_map(static fn (string $line): string => e($line), explode("\n", implode("\n", $lines)));
        return [
            'subject' => $subject,
            'body' => '<p>' . implode('</p><p>', $paragraphs) . '</p>',
        ];
    }
}
