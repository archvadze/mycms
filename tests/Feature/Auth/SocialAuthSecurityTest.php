<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialAuthSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_provider_is_rejected(): void
    {
        $this->get('/auth/unknown/redirect')->assertNotFound();
        $this->get('/auth/unknown/callback')->assertNotFound();
    }

    public function test_unconfigured_google_does_not_redirect_to_google(): void
    {
        config()->set('services.google.client_id', null);
        config()->set('services.google.client_secret', null);

        $this->get('/auth/google/redirect')
            ->assertRedirect(route('login'));
    }

    public function test_callback_without_oauth_intent_is_rejected(): void
    {
        config()->set('services.google.client_id', 'test-client');
        config()->set('services.google.client_secret', 'test-secret');

        $this->get('/auth/google/callback')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_cannot_connect_social_account(): void
    {
        $this->post('/auth/google/connect')
            ->assertRedirect(route('login'));
    }

    public function test_blocked_user_cannot_connect_social_account(): void
    {
        $user = User::factory()->create([
            'status' => 'blocked',
        ]);

        $this->actingAs($user)
            ->post('/auth/google/connect')
            ->assertForbidden();
    }

    public function test_pending_user_cannot_connect_social_account(): void
    {
        $user = User::factory()->create([
            'status' => 'pending',
        ]);

        $this->actingAs($user)
            ->post('/auth/google/connect')
            ->assertForbidden();
    }

    public function test_social_account_table_enforces_unique_identity(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $first->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);

        $this->expectException(
            \Illuminate\Database\QueryException::class
        );

        $second->socialAccounts()->create([
            'provider' => 'google',
            'provider_user_id' => 'google-user-123',
        ]);
    }
}
