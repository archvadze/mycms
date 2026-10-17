<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource\Pages\ViewEmailMessage;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Resend\Contracts\Client as ResendClient;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailReplyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_uses_visitors_reply_to_address(): void
    {
        Role::findOrCreate(AdminAccess::ADMIN, 'web');

        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $admin->assignRole(AdminAccess::ADMIN);

        $this->actingAs($admin);

        $thread = EmailThread::create([
            'subject' => 'New Contact Message: Test',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        $message = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<original@example.test>',
            'from_email' => 'admin@example.com',
            'to_email' => 'admin@example.com',
            'subject' => 'New Contact Message: Test',
            'attachments' => [],
            'metadata' => [
                'reply_to' => ['visitor@example.com'],
            ],
            'is_read' => false,
            'received_at' => now(),
        ]);

        $emails = Mockery::mock();

        $emails->shouldReceive('send')
            ->once()
            ->with(Mockery::on(
                fn (array $payload): bool =>
                    ($payload['to'] ?? []) === ['visitor@example.com']
                    && ($payload['text'] ?? null) === 'Test reply'
            ))
            ->andReturn((object) ['id' => 'mock-sent-123']);

        $client = Mockery::mock(ResendClient::class);
        $client->emails = $emails;

        $this->app->instance(ResendClient::class, $client);

        Livewire::test(ViewEmailMessage::class, [
            'record' => $message->id,
        ])
            ->callAction('reply', data: [
                'body' => 'Test reply',
            ])
            ->assertHasNoErrors();

        $this->assertDatabaseHas('email_messages', [
            'direction' => 'outbound',
            'to_email' => 'visitor@example.com',
            'message_id' => 'mock-sent-123',
        ]);
    }
}
