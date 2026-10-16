<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailInboundAttachmentGuardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([AdminAccess::ADMIN, AdminAccess::SUPPORT, AdminAccess::EDITOR] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function message(array $attachments, array $metadata = []): EmailMessage
    {
        $thread = EmailThread::create([
            'subject' => 'Inbound attachment test',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        return EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'from_email' => 'sender@example.com',
            'to_email' => 'admin@example.com',
            'subject' => 'Inbound attachment test',
            'attachments' => $attachments,
            'metadata' => $metadata,
        ]);
    }

    private function url(EmailMessage $message, string $id): string
    {
        return route('email-messages.attachments.download', [
            'message' => $message,
            'attachmentId' => $id,
        ]);
    }

    public function test_guest_cannot_access_inbound_attachment(): void
    {
        $message = $this->message([[
            'id' => 'file-1', 'filename' => 'report.pdf',
            'content_type' => 'application/pdf', 'size' => 100,
        ]]);

        $this->get($this->url($message, 'file-1'))->assertRedirect();
    }

    public function test_editor_cannot_access_inbound_attachment(): void
    {
        $message = $this->message([[
            'id' => 'file-1', 'filename' => 'report.pdf',
            'content_type' => 'application/pdf', 'size' => 100,
        ]]);

        $this->actingAs($this->user(AdminAccess::EDITOR))
            ->get($this->url($message, 'file-1'))
            ->assertForbidden();
    }

    public function test_unknown_inbound_attachment_returns_404_without_resend_client(): void
    {
        $message = $this->message([[
            'id' => 'file-1', 'filename' => 'report.pdf',
            'content_type' => 'application/pdf', 'size' => 100,
        ]]);

        $this->actingAs($this->user(AdminAccess::SUPPORT))
            ->get($this->url($message, 'not-present'))
            ->assertNotFound();
    }

    public function test_missing_resend_email_identifier_returns_404_without_api_key(): void
    {
        $message = $this->message([[
            'id' => 'file-1', 'filename' => 'report.pdf',
            'content_type' => 'application/pdf', 'size' => 100,
        ]]);

        $this->actingAs($this->user(AdminAccess::ADMIN))
            ->get($this->url($message, 'file-1'))
            ->assertNotFound();
    }

    public function test_disallowed_inbound_attachment_returns_403_before_resend_request(): void
    {
        $message = $this->message([[
            'id' => 'file-1', 'filename' => 'script.php',
            'content_type' => 'text/plain', 'size' => 100,
        ]], ['resend_email_id' => 'remote-id']);

        $this->actingAs($this->user(AdminAccess::ADMIN))
            ->get($this->url($message, 'file-1'))
            ->assertForbidden();
    }
}
