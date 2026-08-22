<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/client-dashboard/profile');

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_profile_page_renders_predefined_avatar_options(): void
    {
        $user = User::factory()->create([
            'avatar' => 'avatar4.svg',
        ]);

        $response = $this
            ->actingAs($user)
            ->get('/profile');

        $response
            ->assertOk()
            ->assertSee('Choose an Avatar')
            ->assertSee('Select an avatar for your client portal profile.');

        for ($i = 1; $i <= 12; $i++) {
            $response
                ->assertSee('value="avatar' . $i . '.svg"', false)
                ->assertSee(asset('avatars/avatar' . $i . '.svg'), false);
        }

        $this->assertMatchesRegularExpression(
            '/value="avatar4\.svg"[^>]*checked/',
            $response->getContent()
        );
    }

    public function test_authenticated_user_can_choose_a_predefined_avatar(): void
    {
        $user = User::factory()->create([
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'avatar' => 'avatar2.svg',
            'tags' => ['Technology', 'Design'],
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'name' => 'Existing User',
            'email' => 'existing@example.com',
            'phone' => '555-0100',
            'company' => 'Existing Company',
            'country' => 'Georgia',
            'website' => 'https://example.com',
            'social_linkedin' => 'https://linkedin.com/in/existing',
            'social_facebook' => 'https://facebook.com/existing',
            'birthday' => '1990-05-12',
        ]);

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => $user->name,
                'email' => $user->email,
                'avatar' => 'avatar9.svg',
                'phone' => $client->phone,
                'company' => $client->company,
                'country' => $client->country,
                'website' => $client->website,
                'social_linkedin' => $client->social_linkedin,
                'social_facebook' => $client->social_facebook,
                'birthday' => '1990-05-12',
                'tags' => $user->tags,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/client-dashboard/profile');

        $user->refresh();
        $client->refresh();

        $this->assertSame('avatar9.svg', $user->avatar);
        $this->assertSame('Existing User', $user->name);
        $this->assertSame('existing@example.com', $user->email);
        $this->assertSame(['Technology', 'Design'], $user->tags);
        $this->assertSame('555-0100', $client->phone);
        $this->assertSame('Existing Company', $client->company);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch('/profile', [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/client-dashboard/profile');

        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->delete('/profile', [
                'password' => 'password',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->from('/profile')
            ->delete('/profile', [
                'password' => 'wrong-password',
            ]);

        $response
            ->assertSessionHasErrorsIn('userDeletion', 'password')
            ->assertRedirect('/profile');

        $this->assertNotNull($user->fresh());
    }
}
