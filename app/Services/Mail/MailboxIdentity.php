<?php

namespace App\Services\Mail;

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
