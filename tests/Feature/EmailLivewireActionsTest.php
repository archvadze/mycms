<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource\Pages\ComposeEmail;
use App\Filament\Resources\EmailMessageResource\Pages\DraftEmailMessages;
use App\Filament\Resources\EmailMessageResource\Pages\SentEmailMessages;
use App\Models\EmailMessage;
use App\Models\User;
use App\Services\MailSettings;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailLivewireActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(AdminAccess::ADMIN, 'web');
        $this->actingAs($this->admin());
        $this->mock(MailSettings::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')->andReturn('Archvadze');
        });
        Mail::fake();
    }

    public function test_compose_livewire_saves_and_sends_draft_only_once(): void
    {
        Livewire::test(ComposeEmail::class)
            ->set('data.to', ['client@example.com'])
            ->set('data.subject', 'Livewire draft')
            ->set('data.body', '<p>Draft body</p>')
            ->call('saveDraft')
            ->assertHasNoErrors();

        $draft = EmailMessage::query()->where('status', 'draft')->sole();
        $this->assertSame(['client@example.com'], $draft->recipients);
        Mail::assertNothingSent();

        Livewire::withQueryParams(['draft' => (string) $draft->id])
            ->test(ComposeEmail::class)
            ->assertSet('draftId', $draft->id)
            ->call('sendEmail')
            ->assertHasNoErrors();

        $this->assertSame('sent', $draft->fresh()->status);
        $this->assertSame(1, $draft->fresh()->send_attempts);
        $this->assertDatabaseCount('email_messages', 1);
        Mail::assertSent(\App\Mail\OutgoingEmail::class, 1);
    }

    public function test_drafts_livewire_delete_action_removes_draft(): void
    {
        $draft = app(\App\Services\Mail\EmailSendingService::class)->saveDraft(
            [], [], [], 'Delete draft', '<p>Remove me</p>'
        );

        Livewire::test(DraftEmailMessages::class)
            ->callTableAction('deleteMessage', $draft)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('email_messages', ['id' => $draft->id]);
    }

    public function test_sent_livewire_delete_action_removes_sent_record(): void
    {
        $message = app(\App\Services\Mail\EmailSendingService::class)->sendMessage(
            ['client@example.com'], [], [], 'Delete sent', '<p>Already sent</p>'
        );

        Livewire::test(SentEmailMessages::class)
            ->callTableAction('deleteMessage', $message)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('email_messages', ['id' => $message->id]);
    }

    private function admin(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole(AdminAccess::ADMIN);

        return $user;
    }
}
