<?php

namespace Tests\Feature;

use App\Listeners\HandleReceivedEmail;
use App\Models\EmailMessage;
use App\Models\EmailThread;
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

        return new class($client, $received) extends HandleReceivedEmail
        {
            public function __construct(
                ResendClient $resend,
                private object $fakeReceived
            ) {
                parent::__construct($resend);
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
}