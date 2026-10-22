<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource\Pages\DraftEmailMessages;
use App\Filament\Resources\EmailMessageResource\Pages\ListEmailMessages;
use App\Filament\Resources\EmailMessageResource\Pages\SentEmailMessages;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailMailboxFilterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(AdminAccess::ADMIN, 'web');

        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $admin->assignRole(AdminAccess::ADMIN);
        $this->actingAs($admin);
    }

    private function message(
        string $direction,
        string $mailbox,
        string $status = 'sent'
    ): EmailMessage {
        $thread = EmailThread::create([
            'subject' => 'Mailbox filter test',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        return EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => $direction,
            'source' => 'test',
            'status' => $status,
            'from_email' => $direction === 'inbound'
                ? 'client@example.test'
                : $mailbox,
            'to_email' => $direction === 'inbound'
                ? $mailbox
                : 'client@example.test',
            'subject' => 'Mailbox filter test',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);
    }

    public function test_inbox_filters_by_recipient_mailbox(): void
    {
        $admin = $this->message('inbound', 'admin@archvadze.com');
        $support = $this->message('inbound', 'support@archvadze.com');

        Livewire::test(ListEmailMessages::class)
            ->filterTable('mailbox', 'admin@archvadze.com')
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$support]);
    }

    public function test_sent_filters_by_sender_mailbox(): void
    {
        $admin = $this->message('outbound', 'admin@archvadze.com');
        $support = $this->message('outbound', 'support@archvadze.com');

        Livewire::test(SentEmailMessages::class)
            ->filterTable('mailbox', 'support@archvadze.com')
            ->assertCanSeeTableRecords([$support])
            ->assertCanNotSeeTableRecords([$admin]);
    }

    public function test_drafts_filter_by_sender_mailbox(): void
    {
        $admin = $this->message('outbound', 'admin@archvadze.com', 'draft');
        $support = $this->message('outbound', 'support@archvadze.com', 'draft');

        Livewire::test(DraftEmailMessages::class)
            ->filterTable('mailbox', 'admin@archvadze.com')
            ->assertCanSeeTableRecords([$admin])
            ->assertCanNotSeeTableRecords([$support]);
    }

    public function test_inbox_filter_includes_secondary_recipients(): void
    {
        $message = $this->message('inbound', 'admin@archvadze.com');

        $message->update([
            'recipients' => [
                'admin@archvadze.com',
                'support@archvadze.com',
            ],
        ]);

        Livewire::test(ListEmailMessages::class)
            ->filterTable('mailbox', 'support@archvadze.com')
            ->assertCanSeeTableRecords([$message]);
    }

    public function test_inbox_filter_matches_earlier_message_in_same_thread(): void
    {
        $adminMessage = $this->message('inbound', 'admin@archvadze.com');
        $supportMessage = $this->message('inbound', 'support@archvadze.com');
        $unrelated = $this->message('inbound', 'support@archvadze.com');

        $supportMessage->update([
            'email_thread_id' => $adminMessage->email_thread_id,
        ]);

        Livewire::test(ListEmailMessages::class)
            ->filterTable('mailbox', 'admin@archvadze.com')
            ->assertCanSeeTableRecords([$supportMessage])
            ->assertCanNotSeeTableRecords([$adminMessage, $unrelated]);
    }
}
