<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailAttachmentSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        foreach ([AdminAccess::ADMIN, AdminAccess::SUPPORT, AdminAccess::EDITOR, AdminAccess::CLIENT] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);
        return $user;
    }

    private function outbound(array $attachment): EmailMessage
    {
        $thread = EmailThread::create(['subject' => 'Test', 'status' => 'open', 'last_message_at' => now()]);
        return EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'outbound',
            'status' => 'sent',
            'source' => 'smtp',
            'from_email' => 'admin@example.com',
            'to_email' => 'client@example.com',
            'subject' => 'Test',
            'attachments' => [$attachment],
            'metadata' => [],
        ]);
    }

    public function test_admin_can_download_private_outbound_attachment(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('email-outgoing/report.pdf', 'example-pdf-content');
        $file = ['path' => 'email-outgoing/report.pdf', 'filename' => 'report.pdf', 'content_type' => 'application/pdf', 'size' => 19];
        $message = $this->outbound($file);
        $id = hash('sha256', $file['path']);

        $this->actingAs($this->user(AdminAccess::ADMIN))
            ->get(route('email-messages.attachments.download', ['message' => $message, 'attachmentId' => $id]))
            ->assertOk()
            ->assertDownload('report.pdf');
    }

    public function test_support_cannot_download_outbound_attachment(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('email-outgoing/report.pdf', 'example-pdf-content');
        $file = ['path' => 'email-outgoing/report.pdf', 'filename' => 'report.pdf', 'content_type' => 'application/pdf', 'size' => 19];
        $message = $this->outbound($file);
        $id = hash('sha256', $file['path']);

        $this->actingAs($this->user(AdminAccess::SUPPORT))
            ->get(route('email-messages.attachments.download', ['message' => $message, 'attachmentId' => $id]))
            ->assertForbidden();
    }

    public function test_wrong_attachment_identifier_returns_not_found(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('email-outgoing/report.pdf', 'example-pdf-content');
        $file = ['path' => 'email-outgoing/report.pdf', 'filename' => 'report.pdf', 'content_type' => 'application/pdf', 'size' => 19];
        $message = $this->outbound($file);

        $this->actingAs($this->user(AdminAccess::ADMIN))
            ->get(route('email-messages.attachments.download', ['message' => $message, 'attachmentId' => 'invalid']))
            ->assertNotFound();
    }
}
