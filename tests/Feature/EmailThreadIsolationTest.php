<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource\Pages\ViewEmailMessage;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailThreadIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversation_displays_only_its_own_messages(): void
    {
        Role::findOrCreate(AdminAccess::ADMIN, 'web');

        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $admin->assignRole(AdminAccess::ADMIN);
        $this->actingAs($admin);

        $threadA = EmailThread::create([
            'subject' => 'Conversation A',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $threadB = EmailThread::create([
            'subject' => 'Conversation B',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $makeMessage = function (
            EmailThread $thread,
            string $direction,
            string $body
        ): EmailMessage {
            return EmailMessage::create([
                'email_thread_id' => $thread->id,
                'direction' => $direction,
                'source' => 'resend',
                'from_email' => 'sender@example.test',
                'to_email' => 'admin@example.test',
                'subject' => $thread->subject,
                'text_body' => $body,
                'attachments' => [],
                'metadata' => [],
                'is_read' => false,
                'received_at' => now(),
            ]);
        };

        $first = $makeMessage(
            $threadA,
            'inbound',
            'THREAD_A_FIRST_UNIQUE'
        );

        $makeMessage(
            $threadA,
            'outbound',
            'THREAD_A_REPLY_UNIQUE'
        );

        $makeMessage(
            $threadB,
            'inbound',
            'THREAD_B_PRIVATE_UNIQUE'
        );

        Livewire::test(ViewEmailMessage::class, [
            'record' => $first->id,
        ])
            ->assertSee('THREAD_A_FIRST_UNIQUE')
            ->assertSee('THREAD_A_REPLY_UNIQUE')
            ->assertDontSee('THREAD_B_PRIVATE_UNIQUE');
    }
}
