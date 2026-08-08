<?php

namespace Tests\Feature;

use App\Listeners\HandleReceivedEmail;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Services\MailSettings;
use App\Services\Mail\AttachmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Resend\Contracts\Client as ResendClient;
use Resend\Laravel\Events\EmailReceived;
use Tests\TestCase;

class HandleReceivedEmailTest extends TestCase
{
    use RefreshDatabase;

    private function listenerWithFakeReceivedEmail(object $received): HandleReceivedEmail
    {
        $client = $this->createMock(ResendClient::class);
        $mailSettings = app(MailSettings::class);
        $attachmentPolicy = app(AttachmentPolicy::class);

        return new class(
            $client,
            $mailSettings,
            $attachmentPolicy,
            $received
        ) extends HandleReceivedEmail
        {
            public function __construct(
                ResendClient $resend,
                MailSettings $mailSettings,
                AttachmentPolicy $attachmentPolicy,
                private object $fakeReceived
            ) {
                parent::__construct(
                    $resend,
                    $mailSettings,
                    $attachmentPolicy
                );
            }

            protected function retrieveReceivedEmail(string $emailId): object
            {
                return $this->fakeReceived;
            }
        };
    }
    
    public function test_received_email_creates_thread_and_message(): void
    {
        $received = (object) [
            'id' => 'test-email-001',
            'from' => 'Test Sender <sender@example.com>',
            'to' => ['admin@archvadze.com'],
            'created_at' => now()->toISOString(),
            'subject' => 'Inbox test',
            'html' => '<p>Hello from test</p>',
            'text' => 'Hello from test',
            'message_id' => '<test-email-001@example.com>',
            'headers' => [],
            'reply_to' => null,
            'cc' => null,
            'bcc' => null,
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $listener->handle(new EmailReceived([
            'type' => 'email.received',
            'data' => [
                'email_id' => 'test-email-001',
            ],
        ]));

        $this->assertDatabaseCount('email_threads', 1);
        $this->assertDatabaseCount('email_messages', 1);

        $message = EmailMessage::firstOrFail();

        $this->assertSame('inbound', $message->direction);
        $this->assertSame('resend', $message->source);
        $this->assertSame('Test Sender', $message->from_name);
        $this->assertSame('sender@example.com', $message->from_email);
        $this->assertSame('admin@archvadze.com', $message->to_email);
        $this->assertSame('Inbox test', $message->subject);
        $this->assertSame('Hello from test', $message->text_body);
        $this->assertSame('<p>Hello from test</p>', $message->html_body);
        $this->assertFalse($message->is_read);

        $this->assertSame(
            'Inbox test',
            EmailThread::firstOrFail()->subject
        );
    }

    public function test_duplicate_received_email_is_not_stored_twice(): void
    {
        $received = (object) [
            'id' => 'test-email-duplicate',
            'from' => 'Sender <sender@example.com>',
            'to' => ['admin@archvadze.com'],
            'created_at' => now()->toISOString(),
            'subject' => 'Duplicate test',
            'html' => null,
            'text' => 'Duplicate body',
            'message_id' => '<duplicate@example.com>',
            'headers' => [],
            'reply_to' => null,
            'cc' => null,
            'bcc' => null,
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $event = new EmailReceived([
            'type' => 'email.received',
            'data' => [
                'email_id' => 'test-email-duplicate',
            ],
        ]);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('email_threads', 1);
        $this->assertDatabaseCount('email_messages', 1);
    }

    public function test_received_reply_reuses_existing_thread_from_references(): void
    {
        $thread = EmailThread::create([
            'subject' => 'Original subject',
            'status' => 'open',
            'last_message_at' => now()->subMinute(),
        ]);

        EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<original-message@example.com>',
            'from_name' => 'Original Sender',
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@archvadze.com',
            'subject' => 'Original subject',
            'text_body' => 'Original body',
            'html_body' => null,
            'attachments' => [],
            'metadata' => [],
            'is_read' => true,
            'received_at' => now()->subMinute(),
        ]);

        $received = (object) [
            'id' => 'test-email-reply',
            'from' => 'Test Sender <sender@example.com>',
            'to' => ['admin@archvadze.com'],
            'created_at' => now()->toISOString(),
            'subject' => 'Re: Original subject',
            'html' => '<p>Reply body</p>',
            'text' => 'Reply body',
            'message_id' => '<reply-message@example.com>',
            'headers' => [
                'references' => '<older-message@example.com> <original-message@example.com>',
            ],
            'reply_to' => null,
            'cc' => null,
            'bcc' => null,
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $listener->handle(new EmailReceived([
            'type' => 'email.received',
            'data' => [
                'email_id' => 'test-email-reply',
            ],
        ]));

        $this->assertDatabaseCount('email_threads', 1);
        $this->assertDatabaseCount('email_messages', 2);

        $reply = EmailMessage::query()
            ->where('message_id', '<reply-message@example.com>')
            ->firstOrFail();

        $this->assertSame($thread->id, $reply->email_thread_id);
        $this->assertSame('inbound', $reply->direction);
        $this->assertSame('Re: Original subject', $reply->subject);

        $thread->refresh();

        $this->assertNotNull($thread->last_message_at);
    }

    public function test_received_reply_reuses_existing_thread_from_in_reply_to(): void
    {
        $thread = EmailThread::create([
            'subject' => 'Another subject',
            'status' => 'open',
            'last_message_at' => now()->subMinute(),
        ]);

        EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'outbound',
            'source' => 'resend',
            'message_id' => '<outbound-message@example.com>',
            'from_name' => 'Archvadze',
            'from_email' => 'admin@archvadze.com',
            'to_email' => 'sender@example.com',
            'subject' => 'Re: Another subject',
            'text_body' => 'Outbound body',
            'html_body' => null,
            'attachments' => [],
            'metadata' => [],
            'is_read' => true,
            'sent_at' => now()->subMinute(),
        ]);

        $received = (object) [
            'id' => 'test-email-in-reply-to',
            'from' => 'Test Sender <sender@example.com>',
            'to' => ['admin@archvadze.com'],
            'created_at' => now()->toISOString(),
            'subject' => 'Re: Another subject',
            'html' => null,
            'text' => 'Reply via In-Reply-To',
            'message_id' => '<reply-in-reply-to@example.com>',
            'headers' => [
                'in-reply-to' => '<outbound-message@example.com>',
            ],
            'reply_to' => null,
            'cc' => null,
            'bcc' => null,
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $listener->handle(new EmailReceived([
            'type' => 'email.received',
            'data' => [
                'email_id' => 'test-email-in-reply-to',
            ],
        ]));

        $this->assertDatabaseCount('email_threads', 1);
        $this->assertDatabaseCount('email_messages', 2);

        $reply = EmailMessage::query()
            ->where('message_id', '<reply-in-reply-to@example.com>')
            ->firstOrFail();

        $this->assertSame($thread->id, $reply->email_thread_id);
    }
}
