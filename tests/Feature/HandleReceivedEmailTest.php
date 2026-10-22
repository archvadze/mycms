<?php

namespace Tests\Feature;

use App\Listeners\HandleReceivedEmail;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Services\Mail\AttachmentPolicy;
use App\Services\MailSettings;
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
                if ($this->fakeReceived instanceof \Throwable) {
                    throw $this->fakeReceived;
                }

                return $this->fakeReceived;
            }
        };
    }

    public function test_retrieval_failure_is_propagated_for_webhook_retry(): void
    {
        $listener = $this->listenerWithFakeReceivedEmail(
            new \RuntimeException('Simulated Resend API failure')
        );

        $event = new EmailReceived([
            'type' => 'email.received',
            'data' => [
                'email_id' => 'temporary-failure-test',
            ],
        ]);

        try {
            $listener->handle($event);
            $this->fail('Retrieval failure was swallowed.');
        } catch (\RuntimeException $exception) {
            $this->assertSame(
                'Simulated Resend API failure',
                $exception->getMessage()
            );
        }

        $this->assertDatabaseCount('email_threads', 0);
        $this->assertDatabaseCount('email_messages', 0);
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
            'reply_to' => 'reply@example.com',
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
            'test-email-001',
            data_get($message->metadata, 'resend_email_id')
        );

        $this->assertSame(
            'reply@example.com',
            data_get($message->metadata, 'reply_to')
        );

        $this->assertNull(
            data_get($message->metadata, 'in_reply_to')
        );

        $this->assertNull(
            data_get($message->metadata, 'references')
        );

        $this->assertArrayNotHasKey(
            'headers',
            $message->metadata
        );

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

        $this->assertSame(
            'test-email-reply',
            data_get($reply->metadata, 'resend_email_id')
        );

        $this->assertSame(
            '<older-message@example.com> <original-message@example.com>',
            data_get($reply->metadata, 'references')
        );

        $this->assertNull(
            data_get($reply->metadata, 'in_reply_to')
        );

        $this->assertArrayNotHasKey(
            'headers',
            $reply->metadata
        );

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

        $this->assertSame(
            'test-email-in-reply-to',
            data_get($reply->metadata, 'resend_email_id')
        );

        $this->assertSame(
            '<outbound-message@example.com>',
            data_get($reply->metadata, 'in_reply_to')
        );

        $this->assertNull(
            data_get($reply->metadata, 'references')
        );

        $this->assertArrayNotHasKey(
            'headers',
            $reply->metadata
        );
    }

    public function test_closed_thread_reopens_when_inbound_reply_is_received(): void
    {
        $thread = EmailThread::create([
            'subject' => 'Closed subject',
            'status' => 'closed',
            'last_message_at' => now()->subMinute(),
        ]);

        EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'outbound',
            'source' => 'resend',
            'message_id' => '<closed-thread-outbound@example.com>',
            'from_name' => 'Archvadze',
            'from_email' => 'admin@archvadze.com',
            'to_email' => 'sender@example.com',
            'subject' => 'Re: Closed subject',
            'text_body' => 'Outbound body',
            'html_body' => null,
            'attachments' => [],
            'metadata' => [],
            'is_read' => true,
            'sent_at' => now()->subMinute(),
        ]);

        $received = (object) [
            'id' => 'test-email-reopens-closed',
            'from' => 'Test Sender <sender@example.com>',
            'to' => ['admin@archvadze.com'],
            'created_at' => now()->toISOString(),
            'subject' => 'Re: Closed subject',
            'html' => null,
            'text' => 'Reply to closed thread',
            'message_id' => '<closed-thread-reply@example.com>',
            'headers' => [
                'in-reply-to' => '<closed-thread-outbound@example.com>',
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
                'email_id' => 'test-email-reopens-closed',
            ],
        ]));

        $this->assertSame('open', $thread->fresh()->status);
        $this->assertDatabaseHas('email_messages', [
            'email_thread_id' => $thread->id,
            'message_id' => '<closed-thread-reply@example.com>',
            'is_read' => false,
        ]);
    }


    public function test_contact_notification_stores_actor_name_and_type(): void
    {
        $received = (object) [
            'from' => 'admin@example.com',
            'to' => ['admin@example.com'],
            'created_at' => now()->toISOString(),
            'subject' => 'New Contact Message: Test',
            'text' => 'Contact message',
            'html' => null,
            'message_id' => '<contact-actor-test@example.com>',
            'headers' => [
                'X-App-Notification-Type' => 'contact',
                'X-App-Actor-Name' => 'Contact Visitor',
            ],
            'reply_to' => ['visitor@example.com'],
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $listener->handle(new EmailReceived([
            'type' => 'email.received',
            'data' => ['email_id' => 'contact-actor-test'],
        ]));

        $message = EmailMessage::firstOrFail();

        $this->assertSame('Contact Visitor', $message->from_name);
        $this->assertSame('admin@example.com', $message->from_email);
        $this->assertSame('visitor@example.com', $message->replyRecipient());
        $this->assertSame('contact', $message->notificationType());
        $this->assertSame('contact', $message->metadata['notification_type']);
    }

    public function test_regular_email_preserves_original_sender(): void
    {
        $received = (object) [
            'from' => 'External Sender <sender@example.com>',
            'to' => ['admin@example.com'],
            'created_at' => now()->toISOString(),
            'subject' => 'Ordinary email',
            'text' => 'Hello',
            'html' => null,
            'message_id' => '<regular-actor-test@example.com>',
            'headers' => [
                'X-App-Actor-Name' => 'Fake Contact Name',
            ],
            'reply_to' => [],
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $listener->handle(new EmailReceived([
            'type' => 'email.received',
            'data' => ['email_id' => 'regular-actor-test'],
        ]));

        $message = EmailMessage::firstOrFail();

        $this->assertSame('External Sender', $message->from_name);
        $this->assertSame('sender@example.com', $message->from_email);
        $this->assertSame('regular', $message->notificationType());
    }

    public function test_multiple_inbound_recipients_are_preserved_without_duplicates(): void
    {
        $received = (object) [
            'id' => 'multi-recipient-001',
            'from' => 'Customer <customer@example.com>',
            'to' => [
                'admin@archvadze.com',
                'support@archvadze.com',
                'ADMIN@ARCHVADZE.COM',
            ],
            'created_at' => now()->toISOString(),
            'subject' => 'Shared mailbox message',
            'text' => 'Message for both mailboxes',
            'html' => null,
            'message_id' => '<multi-recipient-001@example.com>',
            'headers' => [],
            'reply_to' => null,
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $event = new EmailReceived([
            'type' => 'email.received',
            'data' => [
                'email_id' => 'multi-recipient-001',
            ],
        ]);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('email_messages', 1);
        $this->assertDatabaseCount('email_threads', 1);

        $message = EmailMessage::query()->sole();

        $this->assertSame('admin@archvadze.com', $message->to_email);
        $this->assertSame([
            'admin@archvadze.com',
            'support@archvadze.com',
        ], $message->recipients);

        $this->assertSame('inbound', $message->direction);
        $this->assertNotNull($message->email_thread_id);
    }

    public function test_inbound_cc_recipients_are_preserved_without_duplicates(): void
    {
        $received = (object) [
            'id' => 'cc-recipient-001',
            'from' => 'Customer <customer@example.com>',
            'to' => ['admin@archvadze.com'],
            'cc' => [
                'support@archvadze.com',
                'SUPPORT@ARCHVADZE.COM',
                'invalid-address',
            ],
            'created_at' => now()->toISOString(),
            'subject' => 'CC routing test',
            'text' => 'Message with CC recipients',
            'html' => null,
            'message_id' => '<cc-recipient-001@example.com>',
            'headers' => [],
            'reply_to' => null,
            'attachments' => [],
        ];

        $listener = $this->listenerWithFakeReceivedEmail($received);

        $event = new EmailReceived([
            'type' => 'email.received',
            'data' => ['email_id' => 'cc-recipient-001'],
        ]);

        $listener->handle($event);
        $listener->handle($event);

        $this->assertDatabaseCount('email_messages', 1);
        $this->assertDatabaseCount('email_threads', 1);

        $message = EmailMessage::query()->sole();

        $this->assertSame(
            ['admin@archvadze.com'],
            $message->recipients
        );

        $this->assertSame(
            ['support@archvadze.com'],
            $message->cc_recipients
        );

        $this->assertSame(
            'admin@archvadze.com',
            $message->to_email
        );
    }
}
