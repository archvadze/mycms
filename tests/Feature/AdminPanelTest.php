<?php
namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Client', 'guard_name' => 'web']);
    }

    public function test_admin_panel_redirects_unauthenticated(): void
    {
        $response = $this->get('/manage');
        $response->assertRedirect('/login');
    }

    public function test_client_cannot_access_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        $user->update(['status' => 'active']);

        $response = $this->actingAs($user)->get('/manage');
        $response->assertStatus(403);
    }

    public function test_super_admin_can_access_admin_panel(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Super Admin');
        $user->update(['status' => 'active']);
        $user->markEmailAsVerified();

        $response = $this->actingAs($user)->get('/manage');
        $response->assertStatus(200);
    }

    public function test_unverified_admin_cannot_access_panel(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('Super Admin');
        $user->update(['status' => 'active']);

        $response = $this->actingAs($user)->get('/manage');
        $response->assertStatus(403);
    }
}
