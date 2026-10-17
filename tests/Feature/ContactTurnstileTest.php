<?php

namespace Tests\Feature;

use App\Mail\NewContactAdminMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactTurnstileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('turnstile.enabled', true);
        config()->set('turnstile.site_key', 'test-site-key');
        config()->set('turnstile.secret_key', 'test-secret');
    }

    private function payload(): array
    {
        return [
            'name' => 'Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Project inquiry',
            'message' => 'Hello from the contact form.',
            'contact_website' => '',
            'cf-turnstile-response' => 'test-token',
        ];
    }

    public function test_valid_token_allows_contact_message(): void
    {
        Mail::fake();

        Http::fake([
            'challenges.cloudflare.com/*' =>
                Http::response(['success' => true], 200),
        ]);

        $this->post(route('contact.send'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('contact_messages', 1);
        Mail::assertQueued(NewContactAdminMail::class);
    }

    public function test_invalid_token_blocks_contact_message(): void
    {
        Mail::fake();

        Http::fake([
            'challenges.cloudflare.com/*' =>
                Http::response(['success' => false], 200),
        ]);

        $this->post(route('contact.send'), $this->payload())
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_missing_site_key_blocks_contact_message(): void
    {
        Mail::fake();
        Http::fake();

        config()->set('turnstile.site_key', '');

        $this->post(route('contact.send'), $this->payload())
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
    }

    public function test_missing_token_blocks_contact_message(): void
    {
        Mail::fake();
        Http::fake();

        $payload = $this->payload();
        unset($payload['cf-turnstile-response']);

        $this->post(route('contact.send'), $payload)
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingOutgoing();
        Http::assertNothingSent();
    }

    public function test_cloudflare_error_blocks_contact_message(): void
    {
        Mail::fake();

        Http::fake([
            'challenges.cloudflare.com/*' =>
                Http::response([], 503),
        ]);

        $this->post(route('contact.send'), $this->payload())
            ->assertSessionHasErrors('cf-turnstile-response');

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingOutgoing();
    }
}
