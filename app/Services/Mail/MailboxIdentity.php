<?php

namespace App\Services\Mail;

use App\Models\EmailMessage;
use App\Services\MailSettings;
use RuntimeException;

final class MailboxIdentity
{
    public static function composeOptions(MailSettings $settings): array
    {
        $default = $settings->senderEmail();
        $options = [$default => $default . ' (Primary)'];
        if (strtolower($default) === 'admin@archvadze.com') {
            $options['support@archvadze.com'] = 'support@archvadze.com (Support)';
        }

        return $options;
    }


    public static function replyOptions(EmailMessage $message, MailSettings $settings): array
    {
        $allowed = self::composeOptions($settings);
        $recipients = array_merge(
            [$message->to_email],
            $message->recipients ?? []
        );

        $options = [];

        foreach ($recipients as $recipient) {
            if (! is_string($recipient)) {
                continue;
            }

            foreach ($allowed as $address => $label) {
                if (strcasecmp(trim($recipient), $address) === 0) {
                    $options[$address] = $label;
                }
            }
        }

        if ($options === []) {
            $primary = $settings->senderEmail();
            $options[$primary] = $allowed[$primary];
        }

        return $options;
    }

    /** Only an explicitly allowed interactive identity may be chosen. */
    public static function resolve(?string $requested, MailSettings $settings): array
    {
        $address = $requested ?? $settings->senderEmail();
        if (! is_string($address) || ! array_key_exists($address, self::composeOptions($settings))) {
            throw new RuntimeException('Sender address is not permitted for Compose.');
        }

        return [
            'email' => $address,
            'name' => $address === 'support@archvadze.com'
                ? 'Archvadze Support'
                : $settings->senderName(),
        ];
    }
}
