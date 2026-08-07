<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ClientDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Client', 'guard_name' => 'web']);
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    }

    public function test_client_dashboard_requires_auth(): void
    {
        $response = $this->get('/client-dashboard');
        $response->assertRedirect('/login');
    }

    public function test_client_dashboard_requires_client_role(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $user->update(['status' => 'active']);

        $response = $this->actingAs($user)->get('/client-dashboard');
        $response->assertStatus(403);
    }

    public function test_client_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        $user->update(['status' => 'active']);

        $response = $this->actingAs($user)->get('/client-dashboard');
        $response->assertStatus(200);
    }

    public function test_blocked_client_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        $user->update(['status' => 'blocked']);

        $response = $this->actingAs($user)->get('/client-dashboard');
        $response->assertRedirect('/login');
    }
}
