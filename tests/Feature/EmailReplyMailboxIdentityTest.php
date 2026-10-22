<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Services\Mail\EmailSendingService;
use App\Services\MailSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Resend\Contracts\Client as ResendClient;
use Tests\TestCase;

class EmailReplyMailboxIdentityTest extends TestCase
{
    use RefreshDatabase;

    public static function mailboxCases(): array
    {
        return [
            'admin mailbox' => [
                'admin@archvadze.com',
                [],
                'admin@archvadze.com',
                'Archvadze',
            ],
            'support mailbox' => [
                'support@archvadze.com',
                [],
                'support@archvadze.com',
                'Archvadze Support',
            ],
            'multiple recipients' => [
                'support@archvadze.com',
                ['support@archvadze.com', 'admin@archvadze.com'],
                'support@archvadze.com',
                'Archvadze Support',
            ],
            'legacy unknown mailbox' => [
                'legacy@example.test',
                [],
                'admin@archvadze.com',
                'Archvadze',
            ],
        ];
    }

    #[DataProvider('mailboxCases')]
    public function test_reply_uses_correct_mailbox_identity(
        string $incomingTo,
        array $recipients,
        string $expectedFrom,
        string $expectedName
    ): void {
        $this->mock(MailSettings::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')
                ->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')
                ->andReturn('Archvadze');
            $mock->shouldReceive('replyTo')->andReturn(null);
        });

        $thread = EmailThread::create([
            'subject' => 'Mailbox reply test',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $incoming = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<original@example.test>',
            'from_email' => 'customer@example.test',
            'to_email' => $incomingTo,
            'recipients' => $recipients,
            'subject' => 'Mailbox reply test',
            'text_body' => 'Original message',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);

        $emails = Mockery::mock();
        $emails->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (array $payload) use (
                $expectedFrom,
                $expectedName
            ): bool {
                return ($payload['from'] ?? null)
                    === "{$expectedName} <{$expectedFrom}>"
                    && ($payload['to'] ?? [])
                    === ['customer@example.test']
                    && ($payload['text'] ?? null) === 'Test reply';
            }))
            ->andReturn((object) ['id' => 'mock-reply-id']);

        $client = Mockery::mock(ResendClient::class);
        $client->emails = $emails;
        $this->app->instance(ResendClient::class, $client);

        $reply = app(EmailSendingService::class)
            ->sendReply($incoming, 'Test reply');

        $this->assertSame($expectedFrom, $reply->from_email);
        $this->assertSame($expectedName, $reply->from_name);
        $this->assertSame('sent', $reply->status);
        $this->assertSame($thread->id, $reply->email_thread_id);
        $this->assertSame(1, $reply->send_attempts);
        $this->assertDatabaseCount('email_messages', 2);
    }

    public function test_reply_accepts_selected_allowed_sender(): void
    {
        $this->mock(MailSettings::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')->andReturn('Archvadze');
            $mock->shouldReceive('replyTo')->andReturn(null);
        });

        $thread = EmailThread::create([
            'subject' => 'Multiple mailboxes',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $incoming = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<choose-from@example.test>',
            'from_email' => 'customer@example.test',
            'to_email' => 'admin@archvadze.com',
            'recipients' => [
                'admin@archvadze.com',
                'support@archvadze.com',
            ],
            'subject' => 'Multiple mailboxes',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);

        $emails = Mockery::mock();
        $emails->shouldReceive('send')
            ->once()
            ->with(Mockery::on(fn (array $payload): bool =>
                ($payload['from'] ?? null)
                    === 'Archvadze Support <support@archvadze.com>'
            ))
            ->andReturn((object) ['id' => 'selected-from-test']);

        $client = Mockery::mock(ResendClient::class);
        $client->emails = $emails;
        $this->app->instance(ResendClient::class, $client);

        $reply = app(EmailSendingService::class)->sendReply(
            $incoming,
            'Reply from support',
            'support@archvadze.com'
        );

        $this->assertSame('support@archvadze.com', $reply->from_email);
        $this->assertSame('sent', $reply->status);
        $this->assertDatabaseCount('email_messages', 2);
    }

    public function test_reply_rejects_unauthorized_sender_before_persistence(): void
    {
        $this->mock(MailSettings::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')->andReturn('Archvadze');
        });

        $thread = EmailThread::create([
            'subject' => 'Sender validation',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $incoming = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'from_email' => 'customer@example.test',
            'to_email' => 'admin@archvadze.com',
            'subject' => 'Sender validation',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);

        try {
            app(EmailSendingService::class)->sendReply(
                $incoming,
                'Unauthorized reply',
                'noreply@archvadze.com'
            );

            $this->fail('Unauthorized sender was accepted.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString(
                'not permitted',
                $e->getMessage()
            );
        }

        $this->assertDatabaseCount('email_messages', 1);
    }
}
