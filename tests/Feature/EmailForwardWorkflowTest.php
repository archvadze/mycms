<?php

namespace Tests\Feature;

use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Mail\ForwardEmailPrefill;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailForwardWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function makeMessage(array $attributes = []): EmailMessage
    {
        $thread = EmailThread::create([
            'subject' => 'Original subject',
            'status' => 'open',
            'last_message_at' => now(),
        ]);

        return EmailMessage::create(array_merge([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'from_email' => 'sender@example.test',
            'to_email' => 'team@example.test',
            'subject' => 'Original subject',
            'text_body' => 'Original message',
            'attachments' => [],
            'metadata' => [],
            'received_at' => now(),
        ], $attributes));
    }

    private function loginWithRole(string $role): void
    {
        Role::findOrCreate($role, 'web');
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole($role);
        $this->actingAs($user);
    }

    public function test_prefill_escapes_untrusted_message_content(): void
    {
        $message = $this->makeMessage([
            'subject' => 'Proposal <script>alert(1)</script>',
            'text_body' => '<img src=x onerror=alert(1)>',
        ]);

        $prefill = ForwardEmailPrefill::fromMessage($message);
        $this->assertStringStartsWith('Fwd: ', $prefill['subject']);
        $this->assertStringNotContainsString('<img ', $prefill['body']);
        $this->assertStringContainsString('&lt;img', $prefill['body']);
    }

    public function test_prefill_does_not_duplicate_forward_prefix(): void
    {
        $message = $this->makeMessage(['subject' => 'Fwd: Already forwarded']);
        $this->assertSame('Fwd: Already forwarded', ForwardEmailPrefill::fromMessage($message)['subject']);
    }

    public function test_prefill_converts_html_only_message_to_safe_text(): void
    {
        $message = $this->makeMessage([
            'text_body' => null,
            'html_body' => '<div>Hello <strong>world</strong><script>evil()</script></div>',
        ]);
        $prefill = ForwardEmailPrefill::fromMessage($message);
        $this->assertStringContainsString('Hello world', $prefill['body']);
        $this->assertStringNotContainsString('<script>', $prefill['body']);
    }

    public function test_support_cannot_open_compose_with_forward_query(): void
    {
        $message = $this->makeMessage();
        $this->loginWithRole(AdminAccess::SUPPORT);
        $this->get('/manage/email-messages/compose?forward=' . $message->id)->assertForbidden();
    }
}
