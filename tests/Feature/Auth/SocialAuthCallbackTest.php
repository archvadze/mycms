<?php

namespace Tests\Feature\Auth;

use App\Models\SocialAccount;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SocialAuthCallbackTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleUser(
        string $id,
        ?string $email,
        bool $verified = true
    ): void {
        $socialUser = new SocialiteUser();

        $socialUser->map([
            'id' => $id,
            'name' => 'Google Test User',
            'email' => $email,
        ]);

        $socialUser->setRaw([
            'email_verified' => $verified,
        ]);

        $provider = \Mockery::mock();

        $provider->shouldReceive('user')
            ->once()
            ->andReturn($socialUser);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('google')
            ->andReturn($provider);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Role::firstOrCreate([
            'name' => 'Client',
            'guard_name' => 'web',
        ]);

        config()->set('services.google.client_id', 'test-client-id');
        config()->set('services.google.client_secret', 'test-secret');
    }

    public function test_authenticated_user_can_link_google_identity(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        $this->fakeGoogleUser('linked-google-123', $user->email);

        $response = $this->actingAs($user)
            ->withSession([
                'social_auth_intent' => 'link',
                'social_auth_link_user_id' => $user->id,
            ])
            ->get('/auth/google/callback');

        $response->assertRedirect(route('client-dashboard.profile'));

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'linked-google-123',
        ]);
    }

    public function test_social_identity_cannot_be_linked_to_second_user(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);

        SocialAccount::create([
            'user_id' => $owner->id,
            'provider' => 'google',
            'provider_user_id' => 'owned-google-123',
        ]);

        $this->fakeGoogleUser('owned-google-123', $owner->email);

        $this->actingAs($other)
            ->withSession([
                'social_auth_intent' => 'link',
                'social_auth_link_user_id' => $other->id,
            ])
            ->get('/auth/google/callback')
            ->assertRedirect(route('client-dashboard.profile'));

        $this->assertAuthenticatedAs($other);
        $this->assertSame(
            1,
            SocialAccount::where('provider', 'google')->count()
        );
        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $other->id,
            'provider_user_id' => 'owned-google-123',
        ]);
    }

    public function test_link_callback_rejects_different_session_user(): void
    {
        $first = User::factory()->create();
        $second = User::factory()->create();

        $this->fakeGoogleUser('session-test-google', $first->email);

        $this->actingAs($second)
            ->withSession([
                'social_auth_intent' => 'link',
                'social_auth_link_user_id' => $first->id,
            ])
            ->get('/auth/google/callback')
            ->assertForbidden();

        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_pending_linked_account_cannot_sign_in(): void
    {
        $user = User::factory()->create(['status' => 'pending']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'pending-google-123',
        ]);

        $this->fakeGoogleUser('pending-google-123', $user->email);

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_different_google_identity_cannot_replace_existing_link(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'original-google-123',
        ]);

        $this->fakeGoogleUser('replacement-google-456', $user->email);

        $this->actingAs($user)
            ->withSession([
                'social_auth_intent' => 'link',
                'social_auth_link_user_id' => $user->id,
            ])
            ->get('/auth/google/callback')
            ->assertRedirect(route('client-dashboard.profile'))
            ->assertSessionHas('error');

        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'original-google-123',
        ]);
    }

    public function test_reconnecting_same_google_identity_is_idempotent(): void
    {
        $user = User::factory()->create(['status' => 'active']);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'same-google-123',
        ]);

        $this->fakeGoogleUser('same-google-123', $user->email);

        $this->actingAs($user)
            ->withSession([
                'social_auth_intent' => 'link',
                'social_auth_link_user_id' => $user->id,
            ])
            ->get('/auth/google/callback')
            ->assertRedirect(route('client-dashboard.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 1);
    }

    public function test_legacy_google_fallback_is_disabled_by_default(): void
    {
        config()->set('services.google.legacy_email_fallback', false);

        $user = User::factory()->create([
            'email' => 'legacy-disabled@example.com',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->fakeGoogleUser(
            'unlinked-google-id',
            'legacy-disabled@example.com',
            true
        );

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_existing_verified_google_user_can_sign_in_during_transition(): void
    {
        config()->set('services.google.legacy_email_fallback', true);

        $user = User::factory()->create([
            'email' => 'legacy-google@example.com',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $this->fakeGoogleUser(
            'legacy-google-provider-id',
            'legacy-google@example.com',
            true
        );

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_legacy_fallback_cannot_bypass_existing_google_link(): void
    {
        $user = User::factory()->create([
            'email' => 'linked@example.com',
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'original-google-id',
        ]);

        $this->fakeGoogleUser(
            'different-google-id',
            'linked@example.com',
            true
        );

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error');

        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 1);
        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider_user_id' => 'original-google-id',
        ]);
    }

    public function test_existing_linked_account_can_sign_in(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'google-123',
        ]);

        $this->fakeGoogleUser('google-123', $user->email);

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback');

        $this->assertAuthenticatedAs($user);
    }

    public function test_existing_email_is_not_automatically_linked(): void
    {
        $user = User::factory()->create([
            'email' => 'existing@example.com',
        ]);

        $this->fakeGoogleUser(
            'different-google-id',
            'existing@example.com',
            false
        );

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('social_accounts', 0);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_new_google_user_is_created_with_linked_identity(): void
    {
        $this->fakeGoogleUser(
            'new-google-123',
            'new-google@example.com'
        );

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback');

        $user = User::where('email', 'new-google@example.com')
            ->firstOrFail();

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->hasRole('Client'));
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->client);

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'new-google-123',
        ]);
    }

    public function test_blocked_linked_account_cannot_sign_in(): void
    {
        $user = User::factory()->create([
            'status' => 'blocked',
        ]);

        SocialAccount::create([
            'user_id' => $user->id,
            'provider' => 'google',
            'provider_user_id' => 'blocked-google-123',
        ]);

        $this->fakeGoogleUser(
            'blocked-google-123',
            $user->email
        );

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_unverified_google_email_cannot_register(): void
    {
        $this->fakeGoogleUser(
            'unverified-google-123',
            'unverified@example.com',
            false
        );

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }

    public function test_missing_provider_email_does_not_create_user(): void
    {
        $this->fakeGoogleUser('no-email-google', null);

        $this->withSession([
            'social_auth_intent' => 'login',
        ])->get('/auth/google/callback')
            ->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('social_accounts', 0);
    }
}
