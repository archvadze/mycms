<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SentEmailDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([AdminAccess::SUPER_ADMIN, AdminAccess::ADMIN, AdminAccess::SUPPORT, AdminAccess::EDITOR] as $role) {
            Role::findOrCreate($role, 'web');
        }
    }

    public function test_admin_and_super_admin_can_view_outgoing_message(): void
    {
        $message = $this->message('sent');

        foreach ([AdminAccess::ADMIN, AdminAccess::SUPER_ADMIN] as $role) {
            $this->actingAs($this->user($role))
                ->get($this->url($message))
                ->assertOk()
                ->assertSee('customer@example.test')
                ->assertSee('Sample outgoing message');
        }
    }

    public function test_support_and_editor_cannot_view_outgoing_message(): void
    {
        $message = $this->message('sent');

        foreach ([AdminAccess::SUPPORT, AdminAccess::EDITOR] as $role) {
            $this->actingAs($this->user($role))
                ->get($this->url($message))
                ->assertForbidden();
        }
    }

    public function test_inbound_messages_and_drafts_cannot_be_opened_as_sent_details(): void
    {
        $draft = $this->message('draft');
        $inbound = $this->message('received', 'inbound');
        $this->actingAs($this->user(AdminAccess::ADMIN));

        $this->get($this->url($draft))->assertNotFound();
        $this->get($this->url($inbound))->assertNotFound();
    }

    public function test_email_html_is_never_rendered_as_executable_markup(): void
    {
        $message = $this->message('sent');
        $message->update([
            'text_body' => null,
            'html_body' => '<p>Hello</p><script>alert("x")</script>',
        ]);
        $this->actingAs($this->user(AdminAccess::ADMIN))
            ->get($this->url($message))
            ->assertOk()
            ->assertDontSee('<script>alert("x")</script>', false)
            ->assertSee('Hello');
    }

    public function test_outbound_private_attachment_uses_existing_protected_route(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('email-outgoing/example.pdf', 'test-content');

        $message = $this->message('sent');
        $message->update(['attachments' => [[
            'path' => 'email-outgoing/example.pdf',
            'filename' => 'example.pdf',
            'content_type' => 'application/pdf',
            'size' => strlen('test-content'),
        ]]]);

        $download = route('email-messages.attachments.download', [
            'message' => $message->id,
            'attachmentId' => hash('sha256', 'email-outgoing/example.pdf'),
        ]);

        $this->actingAs($this->user(AdminAccess::ADMIN))
            ->get($this->url($message))
            ->assertOk()
            ->assertSee($download, false);
    }

    private function url(EmailMessage $message): string
    {
        return EmailMessageResource::getUrl('sent-view', ['record' => $message->getKey()]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $user->assignRole($role);

        return $user;
    }

    private function message(string $status, string $direction = 'outbound'): EmailMessage
    {
        $thread = EmailThread::create(['subject' => 'Sample outgoing message', 'status' => 'open']);

        return EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => $direction,
            'source' => 'test',
            'status' => $status,
            'from_email' => 'admin@example.test',
            'to_email' => 'customer@example.test',
            'recipients' => ['customer@example.test'],
            'subject' => 'Sample outgoing message',
            'text_body' => 'Sample body',
            'is_read' => true,
            'sent_at' => $status === 'sent' ? now() : null,
        ]);
    }
}
