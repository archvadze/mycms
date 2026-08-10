<?php

namespace Tests\Feature;

use App\Filament\Resources\OrderResource;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\AdminDashboardOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrderOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Support', 'guard_name' => 'web']);
    }

    public function test_order_status_helpers_use_authoritative_statuses(): void
    {
        $this->assertSame([
            'pending' => 'Pending',
            'contacted' => 'Contacted',
            'accepted' => 'Accepted',
            'rejected' => 'Rejected',
        ], OrderResource::statusOptions());

        $this->assertSame('info', OrderResource::statusColor('contacted'));
        $this->assertSame('Rejected', OrderResource::statusLabel('rejected'));
    }

    public function test_order_status_update_uses_existing_statuses_and_observer_behavior(): void
    {
        Mail::fake();
        $this->actingAs($this->userWithRole('Admin'));

        $order = Order::factory()->create([
            'client_id' => null,
            'status' => 'pending',
            'email' => 'customer@example.com',
            'client_name' => 'Customer Name',
            'domain' => 'example.com',
            'website_type' => 'business',
            'project_description' => 'Build the site',
        ]);

        OrderResource::updateStatus($order, 'contacted');

        $this->assertSame('contacted', $order->fresh()->status);

        OrderResource::updateStatus($order->fresh(), 'accepted');

        $this->assertSame('accepted', $order->fresh()->status);

        $project = $order->project()->with('client')->firstOrFail();

        $this->assertSame('customer@example.com', $project->client->email);

        $this->expectException(InvalidArgumentException::class);

        OrderResource::updateStatus($order->fresh(), 'completed');
    }

    public function test_pending_contacted_and_rejected_orders_can_transition_to_accepted(): void
    {
        Mail::fake();
        $this->actingAs($this->userWithRole('Admin'));

        foreach (['pending', 'contacted', 'rejected'] as $status) {
            $order = Order::factory()->create([
                'status' => $status,
                'domain' => "transition-{$status}.example",
            ]);

            $this->assertTrue(
                OrderResource::canTransitionStatus($order, 'accepted')
            );

            OrderResource::updateStatus($order, 'accepted');

            $this->assertSame('accepted', $order->fresh()->status);
        }
    }

    public function test_accepted_order_cannot_transition_back_to_contacted_or_rejected(): void
    {
        Mail::fake();
        $this->actingAs($this->userWithRole('Admin'));

        $order = Order::factory()->create(['status' => 'accepted']);

        $this->assertFalse(
            OrderResource::canTransitionStatus($order, 'contacted')
        );
        $this->assertFalse(
            OrderResource::canTransitionStatus($order, 'rejected')
        );
        $this->assertFalse(
            OrderResource::canTransitionStatus($order, 'accepted')
        );

        foreach (['contacted', 'rejected'] as $status) {
            try {
                OrderResource::updateStatus($order->fresh(), $status);
                $this->fail('Accepted order transition should be blocked.');
            } catch (InvalidArgumentException) {
                $this->assertSame('accepted', $order->fresh()->status);
            }
        }
    }

    public function test_unchanged_accepted_order_does_not_create_duplicate_project(): void
    {
        Mail::fake();
        $this->actingAs($this->userWithRole('Admin'));

        $order = Order::factory()->create([
            'client_id' => null,
            'status' => 'pending',
            'email' => 'accepted@example.com',
            'domain' => 'accepted.example',
        ]);

        OrderResource::updateStatus($order, 'accepted');

        $this->assertSame(1, Project::where('order_id', $order->id)->count());

        OrderResource::updateStatus($order->fresh(), 'accepted');

        $this->assertSame(1, Project::where('order_id', $order->id)->count());
    }

    public function test_related_orders_by_email_excludes_current_order_and_limits_results(): void
    {
        Mail::fake();

        $current = Order::factory()->create([
            'email' => 'repeat@example.com',
            'created_at' => now(),
        ]);

        $older = Order::factory()->create([
            'email' => 'repeat@example.com',
            'created_at' => now()->subDays(2),
        ]);

        $newer = Order::factory()->create([
            'email' => 'repeat@example.com',
            'created_at' => now()->subDay(),
        ]);

        Order::factory()->create([
            'email' => 'other@example.com',
            'created_at' => now()->subHour(),
        ]);

        $related = OrderResource::relatedOrdersByEmail($current, 1)->get();

        $this->assertCount(1, $related);
        $this->assertTrue($related->contains($newer));
        $this->assertFalse($related->contains($current));
        $this->assertFalse($related->contains($older));
    }

    public function test_order_resource_authorization_is_not_broadened(): void
    {
        Mail::fake();

        $order = Order::factory()->create();

        $support = User::factory()->create(['status' => 'active']);
        $support->assignRole('Support');

        $this->actingAs($support);

        $this->assertFalse(OrderResource::canViewAny());
        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertForbidden();

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Admin');

        $this->actingAs($admin);

        $this->assertTrue(OrderResource::canViewAny());
        $this->get(OrderResource::getUrl('view', ['record' => $order]))
            ->assertOk();
    }

    public function test_dashboard_order_metric_uses_pending_status(): void
    {
        Mail::fake();

        Order::factory()->create(['status' => 'pending']);
        Order::factory()->create(['status' => 'contacted']);
        Order::factory()->create(['status' => 'rejected']);

        $metrics = app(AdminDashboardOverview::class)->orderMetrics();

        $this->assertSame(1, $metrics['pending_orders']);
        $this->assertSame(3, $metrics['total_orders']);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
