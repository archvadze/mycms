<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Resend\Laravel\Events\EmailReceived;
use Tests\TestCase;

class HandleReceivedEmailTest extends TestCase
{
    use RefreshDatabase;

    public function test_received_email_creates_thread_and_message(): void
    {
        event(new EmailReceived([
            'type' => 'email.received',
            'data' => [
                'email_id' => 'test-email-001',
                'message_id' => '<test-email-001@example.com>',
                'from' => 'Test Sender <sender@example.com>',
                'to' => ['admin@archvadze.com'],
                'subject' => 'Inbox test',
                'attachments' => [],
                'created_at' => now()->toISOString(),
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
        $this->assertFalse($message->is_read);

        $this->assertSame(
            'Inbox test',
            EmailThread::firstOrFail()->subject
        );
    }

    public function test_duplicate_received_email_is_not_stored_twice(): void
    {
        $payload = [
            'type' => 'email.received',
            'data' => [
                'email_id' => 'test-email-duplicate',
                'message_id' => '<duplicate@example.com>',
                'from' => 'Sender <sender@example.com>',
                'to' => ['admin@archvadze.com'],
                'subject' => 'Duplicate test',
                'attachments' => [],
                'created_at' => now()->toISOString(),
            ],
        ];

        event(new EmailReceived($payload));
        event(new EmailReceived($payload));

        $this->assertDatabaseCount('email_threads', 1);
        $this->assertDatabaseCount('email_messages', 1);
    }
}
