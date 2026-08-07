<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Spatie\Permission\Models\Role;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

protected function setUp(): void
{
    parent::setUp();

    Role::create([
        'name' => 'Client',
        'guard_name' => 'web',
    ]);
}

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'phone' => '+995555123456',
            'password' => 'Password1!',
            'password_confirmation' => 'Password1!',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect('/');
    }
}
