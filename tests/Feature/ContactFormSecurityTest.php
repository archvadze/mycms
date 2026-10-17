<?php

namespace Tests\Feature;

use App\Mail\NewContactAdminMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ContactFormSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function payload(): array
    {
        return [
            'name' => 'Test Visitor',
            'email' => 'visitor@example.com',
            'subject' => 'Project inquiry',
            'message' => 'I would like to discuss a project.',
            'contact_website' => '',
        ];
    }

    public function test_valid_contact_message_is_stored_and_queued(): void
    {
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.11'])
            ->post(route('contact.send'), $this->payload())
            ->assertRedirect();

        $this->assertDatabaseHas('contact_messages', [
            'email' => 'visitor@example.com',
            'subject' => 'Project inquiry',
        ]);

        Mail::assertQueued(NewContactAdminMail::class);
    }

    public function test_honeypot_blocks_submission_without_sending_mail(): void
    {
        Mail::fake();

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.12'])
            ->post(route('contact.send'), [
                ...$this->payload(),
                'contact_website' => 'https://spam.example',
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('contact_messages', 0);
        Mail::assertNothingOutgoing();
    }

    public function test_contact_mail_sets_visitor_as_reply_to(): void
    {
        $mail = new NewContactAdminMail(
            'Test Visitor',
            'visitor@example.com',
            'Project inquiry',
            'Hello'
        );

        $this->assertSame(
            'visitor@example.com',
            $mail->envelope()->replyTo[0]->address
        );
    }

    public function test_contact_route_limits_repeated_requests(): void
    {
        Mail::fake();

        for ($i = 0; $i < 5; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.13'])
                ->post(route('contact.send'), $this->payload())
                ->assertRedirect();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.13'])
            ->post(route('contact.send'), $this->payload())
            ->assertStatus(429);

        $this->assertDatabaseCount('contact_messages', 5);
    }
}
