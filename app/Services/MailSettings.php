<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Cache;

class MailSettings
{
    private const CACHE_KEY = 'mail.settings';

    public function all(): array
    {
        return Cache::remember(
            self::CACHE_KEY,
            3600,
            fn (): array => SiteSetting::query()
                ->whereIn('key', [
                    'mail_sender_name',
                    'mail_sender_email',
                    'mail_inbox_email',
                    'mail_reply_to',
                    'mail_admin_notification_email',
                    'mail_enabled',
                ])
                ->pluck('value', 'key')
                ->toArray()
        );
    }

    public function senderName(): string
    {
        return $this->value(
            'mail_sender_name',
            config('mail.from.name') ?: config('app.name')
        );
    }

    public function senderEmail(): string
    {
        return $this->value(
            'mail_sender_email',
            config('mail.from.address') ?: config('agency.admin_email')
        );
    }

    public function inboxEmail(): string
    {
        return $this->value(
            'mail_inbox_email',
            config('agency.admin_email')
        );
    }

    public function replyTo(): ?string
    {
        $value = $this->value('mail_reply_to');

        return $value !== '' ? $value : null;
    }

    public function adminNotificationEmail(): string
    {
        return $this->value(
            'mail_admin_notification_email',
            config('agency.admin_email')
        );
    }

    public function enabled(): bool
    {
        $value = $this->value('mail_enabled', '1');

        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    private function value(string $key, ?string $fallback = null): string
    {
        $settings = $this->all();

        $value = trim((string) ($settings[$key] ?? ''));

        if ($value !== '') {
            return $value;
        }

        return (string) ($fallback ?? '');
    }
}
