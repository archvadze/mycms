<?php

namespace Tests\Feature;

use App\Models\SiteSetting;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_mail_settings_use_fallback_values_when_database_is_empty(): void
    {
        config()->set('app.name', 'Fallback App');
        config()->set('mail.from.name', 'Fallback Sender');
        config()->set('mail.from.address', 'fallback@example.com');
        config()->set('agency.admin_email', 'admin@example.com');

        $settings = app(MailSettings::class);

        $this->assertSame('Fallback Sender', $settings->senderName());
        $this->assertSame('fallback@example.com', $settings->senderEmail());
        $this->assertSame('admin@example.com', $settings->inboxEmail());
        $this->assertNull($settings->replyTo());
        $this->assertSame('admin@example.com', $settings->adminNotificationEmail());
        $this->assertTrue($settings->enabled());
    }

    public function test_database_values_override_mail_fallbacks(): void
    {
        SiteSetting::insert([
            [
                'key' => 'mail_sender_name',
                'value' => 'Client Company',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_sender_email',
                'value' => 'mail@client.example',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_inbox_email',
                'value' => 'inbox@client.example',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_reply_to',
                'value' => 'reply@client.example',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_admin_notification_email',
                'value' => 'notifications@client.example',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'mail_enabled',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $settings = app(MailSettings::class);
        $settings->clearCache();

        $this->assertSame('Client Company', $settings->senderName());
        $this->assertSame('mail@client.example', $settings->senderEmail());
        $this->assertSame('inbox@client.example', $settings->inboxEmail());
        $this->assertSame('reply@client.example', $settings->replyTo());
        $this->assertSame(
            'notifications@client.example',
            $settings->adminNotificationEmail()
        );
        $this->assertFalse($settings->enabled());
    }

    public function test_clearing_cache_exposes_updated_database_values(): void
    {
        SiteSetting::create([
            'key' => 'mail_sender_name',
            'value' => 'Old Name',
        ]);

        $settings = app(MailSettings::class);

        $this->assertSame('Old Name', $settings->senderName());

        SiteSetting::where('key', 'mail_sender_name')
            ->update(['value' => 'New Name']);

        $this->assertSame('Old Name', $settings->senderName());

        $settings->clearCache();

        $this->assertSame('New Name', $settings->senderName());
    }
}
