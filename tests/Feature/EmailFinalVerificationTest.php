<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource\Pages\ListEmailMessages;
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

class EmailFinalVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([AdminAccess::ADMIN, AdminAccess::SUPPORT] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_admin_can_delete_one_inbound_message_using_livewire_action(): void
    {
        $this->actingAs($this->user(AdminAccess::ADMIN));
        $target = $this->inbound('Delete this');
        $other = $this->inbound('Keep this');

        Livewire::test(ListEmailMessages::class)
            ->callTableAction('delete_inbound_email', $target)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('email_messages', ['id' => $target->id]);
        $this->assertDatabaseHas('email_messages', ['id' => $other->id]);
    }

    public function test_support_can_delete_inbound_message_under_current_role_policy(): void
    {
        $this->actingAs($this->user(AdminAccess::SUPPORT));
        $target = $this->inbound('Support inbox delete');

        Livewire::test(ListEmailMessages::class)
            ->callTableAction('delete_inbound_email', $target)
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('email_messages', ['id' => $target->id]);
    }

    public function test_allowed_inbound_attachment_redirects_to_mocked_https_resend_url(): void
    {
        $this->actingAs($this->user(AdminAccess::ADMIN));
        $message = $this->inbound('Remote attachment', [[
            'id' => 'att-123',
            'filename' => 'report.pdf',
            'content_type' => 'application/pdf',
            'size' => 100,
        ]], ['resend_email_id' => 'email-123']);

        $this->bindResendAttachmentResponse('https://download.example.test/file.pdf');

        $this->get($this->attachmentUrl($message, 'att-123'))
            ->assertRedirect('https://download.example.test/file.pdf');
    }

    public function test_insecure_inbound_attachment_url_is_rejected(): void
    {
        $this->actingAs($this->user(AdminAccess::ADMIN));
        $message = $this->inbound('Reject insecure URL', [[
            'id' => 'att-123',
            'filename' => 'report.pdf',
            'content_type' => 'application/pdf',
            'size' => 100,
        ]], ['resend_email_id' => 'email-123']);

        $this->bindResendAttachmentResponse('http://download.example.test/file.pdf');

        $this->get($this->attachmentUrl($message, 'att-123'))
            ->assertStatus(502);
    }

    private function bindResendAttachmentResponse(string $downloadUrl): void
    {
        $attachments = Mockery::mock();
        $attachments->shouldReceive('get')
            ->once()
            ->with('email-123', 'att-123')
            ->andReturn((object) ['download_url' => $downloadUrl]);

        $client = Mockery::mock(ResendClient::class);
        $client->emails = (object) [
            'receiving' => (object) ['attachments' => $attachments],
        ];

        $this->app->instance(ResendClient::class, $client);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function inbound(string $subject, array $attachments = [], array $metadata = []): EmailMessage
    {
        $thread = EmailThread::create([
            'subject' => $subject,
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        return EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@example.com',
            'subject' => $subject,
            'is_read' => false,
            'received_at' => now(),
            'attachments' => $attachments,
            'metadata' => $metadata,
        ]);
    }

    private function attachmentUrl(EmailMessage $message, string $id): string
    {
        return route('email-messages.attachments.download', [
            'message' => $message,
            'attachmentId' => $id,
        ]);
    }
}
