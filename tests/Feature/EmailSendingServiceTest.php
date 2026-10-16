<?php

namespace Tests\Feature;

use App\Mail\OutgoingEmail;
use App\Models\EmailMessage;
use App\Services\Mail\EmailSendingService;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Tests\TestCase;

class EmailSendingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_sends_and_records_an_email(): void
    {
        Mail::fake();

        $this->mock(MailSettings::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')
                ->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')
                ->andReturn('Archvadze');
        });

        $message = app(EmailSendingService::class)->send(
            'client@example.com',
            'Business Proposal',
            'Hello, this is a test proposal.'
        );

        $this->assertInstanceOf(EmailMessage::class, $message);

        $this->assertDatabaseHas('email_messages', [
            'id' => $message->id,
            'direction' => 'outbound',
            'to_email' => 'client@example.com',
            'subject' => 'Business Proposal',
        ]);

        $this->assertDatabaseHas('email_threads', [
            'id' => $message->email_thread_id,
            'subject' => 'Business Proposal',
        ]);

        $this->assertNotNull($message->sent_at);
        // $this->assertNotEmpty($message->message_id);

        Mail::assertSent(OutgoingEmail::class, function (OutgoingEmail $mail) {
            return $mail->hasTo('client@example.com')
                && $mail->emailSubject === 'Business Proposal'
                && $mail->emailBody === 'Hello, this is a test proposal.';
        });

    }

    public function test_it_rejects_invalid_recipient(): void
    {
        Mail::fake();

        $this->expectException(RuntimeException::class);

        app(EmailSendingService::class)->send(
            'invalid-email',
            'Test',
            'Test message'
        );

        Mail::assertNothingSent();
    }

    public function test_it_preserves_message_on_send_failure(): void
    {
        $this->mock(MailSettings::class, function ($mock) {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')
                ->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')
                ->andReturn('Archvadze');
        });

        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('Simulated failure'));

        try {
            app(EmailSendingService::class)->send(
                'client@example.com',
                'Test failure',
                'Test body'
            );

            $this->fail('Expected exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString(
                'delivery could not be confirmed',
                $e->getMessage()
            );
        }

        $this->assertDatabaseHas('email_messages', [
            'to_email' => 'client@example.com',
            'status' => 'unknown',
            'send_attempts' => 1,
            'sent_at' => null,
        ]);
    }
}
