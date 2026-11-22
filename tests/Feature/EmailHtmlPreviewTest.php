<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailHtmlPreviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::findOrCreate(AdminAccess::ADMIN, 'web');
        Role::findOrCreate(AdminAccess::SUPPORT, 'web');
    }

    public function test_inbound_html_is_isolated_in_sandboxed_preview(): void
    {
        $thread = EmailThread::create([
            'subject' => 'HTML preview', 'status' => 'open', 'last_message_at' => now(),
        ]);
        $message = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound', 'source' => 'test',
            'message_id' => '<html-preview@example.test>',
            'from_email' => 'sender@example.test',
            'to_email' => 'support@example.test',
            'subject' => 'HTML preview',
            'text_body' => "Line one\nLine two",
            'html_body' => '<h1>Formatted headline</h1><script>window.__emailXss = true</script>',
            'is_read' => false, 'received_at' => now(),
        ]);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $admin->assignRole(AdminAccess::ADMIN);

        $response = $this->actingAs($admin)->get(
            EmailMessageResource::getUrl('view', ['record' => $message->id])
        );

        $response->assertOk()
            ->assertSee('Email HTML preview')
            ->assertSee('sandbox=', false)
            ->assertSee('srcdoc=', false)
            ->assertDontSee('<script>window.__emailXss = true</script>', false);

        $this->assertStringContainsString(
            "frame-src 'self'",
            (string) $response->headers->get('Content-Security-Policy')
        );
    }

    public function test_plain_text_only_messages_remain_readable(): void
    {
        $thread = EmailThread::create([
            'subject' => 'Plain text', 'status' => 'open', 'last_message_at' => now(),
        ]);
        $message = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound', 'source' => 'test',
            'message_id' => '<plain-preview@example.test>',
            'from_email' => 'sender@example.test',
            'to_email' => 'support@example.test',
            'subject' => 'Plain text',
            'text_body' => "First line\nSecond line",
            'html_body' => null,
            'is_read' => false, 'received_at' => now(),
        ]);
        $admin = User::factory()->create(['status' => 'active', 'email_verified_at' => now()]);
        $admin->assignRole(AdminAccess::ADMIN);

        $this->actingAs($admin)->get(
            EmailMessageResource::getUrl('view', ['record' => $message->id])
        )
            ->assertOk()
            ->assertSee('First line')
            ->assertSee('Second line')
            ->assertSee('white-space:pre-wrap', false);
    }
}
