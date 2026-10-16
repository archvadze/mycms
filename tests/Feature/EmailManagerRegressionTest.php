<?php

namespace Tests\Feature;

use App\Mail\OutgoingEmail;
use App\Models\EmailMessage;
use App\Services\Mail\EmailSendingService;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

class EmailManagerRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function configureSender(): void
    {
        $this->mock(MailSettings::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')->andReturn('Archvadze');
        });
    }

    public function test_multiple_to_cc_and_bcc_are_recorded_and_delivered(): void
    {
        Mail::fake();
        $this->configureSender();

        $message = app(EmailSendingService::class)->sendMessage(
            ['first@example.com', 'second@example.com'],
            ['copy@example.com'],
            ['hidden@example.com'],
            'Recipients',
            '<p>Hello recipients</p>',
        );

        $this->assertSame('sent', $message->status);
        $this->assertSame(['first@example.com', 'second@example.com'], $message->recipients);
        $this->assertSame(['copy@example.com'], $message->cc_recipients);
        $this->assertSame(['hidden@example.com'], $message->bcc_recipients);
        Mail::assertSent(OutgoingEmail::class, function (OutgoingEmail $mail): bool {
            return $mail->hasTo('first@example.com')
                && $mail->hasTo('second@example.com')
                && $mail->hasCc('copy@example.com')
                && $mail->hasBcc('hidden@example.com');
        });
    }

    public function test_duplicate_recipients_across_fields_are_rejected(): void
    {
        Mail::fake();
        $this->configureSender();

        try {
            app(EmailSendingService::class)->sendMessage(
                ['First@example.com'], ['first@example.com'], [], 'Duplicate', '<p>Body</p>',
            );
            $this->fail('Expected a duplicate-recipient exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Duplicate recipient', $e->getMessage());
        }

        $this->assertDatabaseCount('email_messages', 0);
        Mail::assertNothingSent();
    }

    public function test_draft_can_be_updated_and_sent_once(): void
    {
        Mail::fake();
        $this->configureSender();
        $sender = app(EmailSendingService::class);

        $draft = $sender->saveDraft([], [], [], 'Initial', '<p>First version</p>');
        $this->assertSame('draft', $draft->status);
        $this->assertNotNull($draft->draft_saved_at);
        Mail::assertNothingSent();

        $updated = $sender->saveDraft(['client@example.com'], [], [], 'Final', '<p>Final version</p>', [], $draft);
        $this->assertSame($draft->id, $updated->id);
        $this->assertSame('Final', $updated->subject);
        $this->assertDatabaseCount('email_messages', 1);

        $sent = $sender->sendMessage(['client@example.com'], [], [], 'Final', '<p>Final version</p>', [], $updated);
        $this->assertSame($draft->id, $sent->id);
        $this->assertSame('sent', $sent->status);
        $this->assertSame(1, $sent->send_attempts);
        Mail::assertSent(OutgoingEmail::class, 1);

        try {
            $sender->sendMessage(['client@example.com'], [], [], 'Final', '<p>Final version</p>', [], $sent->fresh());
            $this->fail('Already sent draft should not be sent again.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already been sent', $e->getMessage());
        }
        Mail::assertSent(OutgoingEmail::class, 1);
    }

    public function test_missing_private_attachment_is_rejected_before_sending(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->configureSender();

        $file = [
            'path' => 'email-outgoing/missing.pdf',
            'filename' => 'missing.pdf',
            'content_type' => 'application/pdf',
            'size' => 128,
        ];

        try {
            app(EmailSendingService::class)->sendMessage(
                ['client@example.com'], [], [], 'Missing file', '<p>Body</p>', [$file],
            );
            $this->fail('Expected missing private attachment to be rejected.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('private storage', $e->getMessage());
        }
        $this->assertDatabaseCount('email_messages', 0);
        Mail::assertNothingSent();
    }

    public function test_private_attachment_metadata_is_persisted(): void
    {
        Mail::fake();
        Storage::fake('local');
        $this->configureSender();
        Storage::disk('local')->put('email-outgoing/sample.pdf', 'test-content');
        $file = [
            'path' => 'email-outgoing/sample.pdf',
            'filename' => 'sample.pdf',
            'content_type' => 'application/pdf',
            'size' => strlen('test-content'),
        ];

        $message = app(EmailSendingService::class)->sendMessage(
            ['client@example.com'], [], [], 'Attachment', '<p>Body</p>', [$file],
        );
        $this->assertSame('sent', $message->status);
        $this->assertSame([$file], $message->attachments);
        Mail::assertSent(OutgoingEmail::class, fn (OutgoingEmail $mail): bool => count($mail->emailFiles) === 1);
    }

    public function test_provider_exception_marks_status_unknown_without_automatic_retry(): void
    {
        $this->configureSender();
        Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('SMTP connection lost'));

        try {
            app(EmailSendingService::class)->sendMessage(
                ['client@example.com'], [], [], 'Failure', '<p>Body</p>',
            );
            $this->fail('Expected uncertain delivery exception.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('could not be confirmed', $e->getMessage());
        }
        $message = EmailMessage::query()->sole();
        $this->assertSame('unknown', $message->status);
        $this->assertSame(1, $message->send_attempts);
        $this->assertNull($message->sent_at);
    }
}
