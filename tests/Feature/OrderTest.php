<?php
namespace Tests\Feature;

use App\Models\User;
use App\Models\Service;
use App\Models\Feature as FeatureModel;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Roles შევქმნათ
        Role::create(['name' => 'Client', 'guard_name' => 'web']);
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    }

    public function test_order_page_requires_auth(): void
    {
        $response = $this->get('/order');
        $response->assertRedirect('/login');
    }

    public function test_order_page_requires_verified_email(): void
    {
        $user = User::factory()->unverified()->create();
        $user->assignRole('Client');

        $response = $this->actingAs($user)->get('/order');
        $response->assertRedirect('/verify-email');
    }

    public function test_verified_client_can_access_order_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('Client');
        $user->update(['status' => 'active']);

        Service::create([
            'name' => 'Test Service',
            'slug' => 'test-service',
            'description' => 'Test',
            'base_price' => 100,
            'status' => true,
        ]);

        $response = $this->actingAs($user)->get('/order');
        $response->assertStatus(200);
    }
}
