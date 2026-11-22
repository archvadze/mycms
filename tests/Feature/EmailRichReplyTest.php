<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource\Pages\ViewEmailMessage;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\User;
use App\Services\Mail\EmailSendingService;
use App\Services\MailSettings;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Mockery;
use Resend\Contracts\Client as ResendClient;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailRichReplyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock(MailSettings::class, function ($mock): void {
            $mock->shouldReceive('enabled')->andReturn(true);
            $mock->shouldReceive('senderEmail')->andReturn('admin@archvadze.com');
            $mock->shouldReceive('senderName')->andReturn('Archvadze');
            $mock->shouldReceive('replyTo')->andReturn(null);
        });
    }

    public function test_reply_modal_sends_sanitized_html_and_text_fallback(): void
    {
        Role::findOrCreate(AdminAccess::ADMIN, 'web');
        $admin = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $admin->assignRole(AdminAccess::ADMIN);
        $this->actingAs($admin);
        $inbound = $this->inbound();

        $captured = null;
        $emails = Mockery::mock();
        $emails->shouldReceive('send')->once()
            ->with(Mockery::on(function (array $payload) use (&$captured): bool {
                $captured = $payload;
                return true;
            }))
            ->andReturn((object) ['id' => 'reply-rich-test-1']);
        $client = Mockery::mock(ResendClient::class);
        $client->emails = $emails;
        $this->app->instance(ResendClient::class, $client);

        Livewire::test(ViewEmailMessage::class, ['record' => $inbound->id])
            ->callAction('reply', data: [
                'body' => '<p>Hello <strong>World</strong>.</p><p>Another <em>line</em>.</p><script>window.xss=1</script>',
            ])->assertHasNoErrors();

        $this->assertIsArray($captured);
        $this->assertStringContainsString('Hello World.', $captured['text']);
        $this->assertStringContainsString('Another line.', $captured['text']);
        $this->assertStringContainsString('<strong>World</strong>', $captured['html']);
        $this->assertStringContainsString('<em>line</em>', $captured['html']);
        $this->assertStringNotContainsString('<script', strtolower($captured['html']));
        $this->assertStringNotContainsString('window.xss=1', $captured['html']);
        $this->assertSame('<original-reply@example.test>', $captured['headers']['In-Reply-To']);
        $this->assertSame(['customer@example.test'], $captured['to']);

        $reply = EmailMessage::query()->where('direction', 'outbound')->sole();
        $this->assertSame($inbound->email_thread_id, $reply->email_thread_id);
        $this->assertSame('sent', $reply->status);
        $this->assertNotNull($reply->html_body);
        $this->assertStringContainsString('<strong>World</strong>', $reply->html_body);
        $this->assertStringNotContainsString('<script', strtolower($reply->html_body));
    }

    public function test_plain_text_service_call_retains_existing_behavior(): void
    {
        $inbound = $this->inbound();
        $captured = null;
        $emails = Mockery::mock();
        $emails->shouldReceive('send')->once()
            ->with(Mockery::on(function (array $payload) use (&$captured): bool {
                $captured = $payload;
                return true;
            }))
            ->andReturn((object) ['id' => 'reply-text-test-2']);
        $client = Mockery::mock(ResendClient::class);
        $client->emails = $emails;
        $this->app->instance(ResendClient::class, $client);

        $reply = app(EmailSendingService::class)->sendReply($inbound, 'Plain reply');
        $this->assertSame('Plain reply', $captured['text']);
        $this->assertArrayNotHasKey('html', $captured);
        $this->assertNull($reply->html_body);
        $this->assertSame('Plain reply', $reply->text_body);
    }

    public function test_empty_rich_editor_content_is_rejected_before_persistence(): void
    {
        $inbound = $this->inbound();
        try {
            app(EmailSendingService::class)->sendReply($inbound, '<p><br></p>', null, true);
            $this->fail('Empty rich text accepted');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('non-empty reply', $e->getMessage());
        }
        $this->assertDatabaseCount('email_messages', 1);
    }

    private function inbound(): EmailMessage
    {
        $thread = EmailThread::create([
            'subject' => 'Reply HTML test',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        return EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<original-reply@example.test>',
            'from_email' => 'customer@example.test',
            'to_email' => 'admin@archvadze.com',
            'subject' => 'Reply HTML test',
            'text_body' => 'Original incoming message',
            'attachments' => [],
            'metadata' => [],
            'received_at' => now(),
            'is_read' => false,
        ]);
    }
}
