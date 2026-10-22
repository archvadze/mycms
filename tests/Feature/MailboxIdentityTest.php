<?php

namespace Tests\Feature;

use App\Mail\OutgoingEmail;
use App\Services\Mail\EmailSendingService;
use App\Services\Mail\MailboxIdentity;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class MailboxIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function settings(): void
    {
        $this->mock(MailSettings::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')->andReturn('Archvadze');
        });
    }

    public function test_compose_options_exclude_noreply(): void
    {
        $this->settings();
        $options = MailboxIdentity::composeOptions(app(MailSettings::class));
        $this->assertArrayHasKey('admin@archvadze.com', $options);
        $this->assertArrayHasKey('support@archvadze.com', $options);
        $this->assertArrayNotHasKey('noreply@archvadze.com', $options);
    }

    public function test_support_sender_is_persisted_and_used_by_mail_transport(): void
    {
        Mail::fake();
        $this->settings();
        $message = app(EmailSendingService::class)->sendMessage(
            ['client@example.com'], [], [], 'Support response', '<p>Hello</p>', [], null,
            'support@archvadze.com'
        );
        $this->assertSame('support@archvadze.com', $message->from_email);
        $this->assertSame('Archvadze Support', $message->from_name);
        Mail::assertSent(OutgoingEmail::class, function (OutgoingEmail $mail): bool {
            return $mail->from[0]['address'] === 'support@archvadze.com';
        });
    }

    public function test_noreply_and_arbitrary_sender_are_rejected_before_persisting(): void
    {
        Mail::fake();
        $this->settings();
        foreach (['noreply@archvadze.com', 'attacker@example.com'] as $address) {
            try {
                app(EmailSendingService::class)->sendMessage(
                    ['client@example.com'], [], [], 'Test', '<p>Body</p>', [], null, $address
                );
                $this->fail('Expected invalid sender to be rejected.');
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('not permitted', $e->getMessage());
            }
        }
        $this->assertDatabaseCount('email_messages', 0);
        Mail::assertNothingSent();
    }

    public function test_draft_keeps_selected_sender(): void
    {
        $this->settings();
        $sender = app(EmailSendingService::class);
        $draft = $sender->saveDraft([], [], [], 'Draft', '<p>Body</p>', [], null, 'support@archvadze.com');
        $this->assertSame('support@archvadze.com', $draft->from_email);
    }
}
