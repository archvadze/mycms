<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmailInboxWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_can_be_marked_read_and_unread(): void
    {
        $thread = EmailThread::create([
            'subject' => 'Read workflow',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $first = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<read-workflow-1@example.com>',
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@example.com',
            'subject' => 'Read workflow',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now()->subMinute(),
        ]);

        $latest = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<read-workflow-2@example.com>',
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@example.com',
            'subject' => 'Read workflow',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);

        $this->assertSame('1', EmailMessageResource::getNavigationBadge());

        EmailMessageResource::markConversationRead($latest);

        $this->assertTrue($first->fresh()->is_read);
        $this->assertTrue($latest->fresh()->is_read);
        $this->assertNull(EmailMessageResource::getNavigationBadge());

        EmailMessageResource::markConversationUnread($latest->fresh());

        $this->assertFalse($first->fresh()->is_read);
        $this->assertFalse($latest->fresh()->is_read);
        $this->assertSame('1', EmailMessageResource::getNavigationBadge());
    }

    public function test_bulk_read_and_status_actions_operate_on_threads(): void
    {
        $openThread = EmailThread::create([
            'subject' => 'Open thread',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $closedThread = EmailThread::create([
            'subject' => 'Closed thread',
            'status' => 'closed',
            'last_message_at' => now(),
        ]);

        $openMessage = EmailMessage::create([
            'email_thread_id' => $openThread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<open-thread@example.com>',
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@example.com',
            'subject' => 'Open thread',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);

        $closedMessage = EmailMessage::create([
            'email_thread_id' => $closedThread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<closed-thread@example.com>',
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@example.com',
            'subject' => 'Closed thread',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);

        $records = EmailMessage::query()
            ->whereKey([$openMessage->id, $closedMessage->id])
            ->get();

        EmailMessageResource::markConversationsRead($records);

        $this->assertTrue($openMessage->fresh()->is_read);
        $this->assertTrue($closedMessage->fresh()->is_read);

        EmailMessageResource::markConversationsUnread($records);

        $this->assertFalse($openMessage->fresh()->is_read);
        $this->assertFalse($closedMessage->fresh()->is_read);

        EmailMessageResource::updateConversationsStatus($records, 'closed');

        $this->assertSame('closed', $openThread->fresh()->status);
        $this->assertSame('closed', $closedThread->fresh()->status);

        EmailMessageResource::updateConversationsStatus($records, 'open');

        $this->assertSame('open', $openThread->fresh()->status);
        $this->assertSame('open', $closedThread->fresh()->status);
    }
}
