<?php

namespace Tests\Feature;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Pages\CreateProject;
use App\Filament\Resources\ProjectResource\Pages\EditProject;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Models\Client;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\AdminDashboardOverview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectOperationsWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Super Admin', 'Admin', 'Editor', 'Support', 'Client'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_project_resource_authorization_matrix(): void
    {
        $project = Project::factory()->create();

        foreach (['Super Admin', 'Admin'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(ProjectResource::getUrl())->assertOk();
            $this->get(ProjectResource::getUrl('view', ['record' => $project]))->assertOk();
        }

        foreach (['Editor', 'Support', 'Client'] as $role) {
            $this->actingAs($this->userWithRole($role));

            $this->get(ProjectResource::getUrl())->assertForbidden();
            $this->get(ProjectResource::getUrl('view', ['record' => $project]))->assertForbidden();
        }
    }

    public function test_project_status_helpers_use_authoritative_schema_statuses(): void
    {
        $this->assertSame([
            'pending' => 'Pending',
            'in_progress' => 'In Progress',
            'review' => 'Review',
            'completed' => 'Completed',
        ], ProjectResource::statusOptions());

        $this->assertSame('Review', ProjectStatus::Review->label());
        $this->assertSame('success', ProjectResource::statusColor('completed'));
    }

    public function test_project_status_update_validates_status_and_records_activity(): void
    {
        $admin = $this->userWithRole('Admin');
        $project = Project::factory()->create(['status' => 'pending']);

        $this->actingAs($admin);

        ProjectResource::updateStatus($project, 'in_progress');

        $this->assertSame('in_progress', $project->fresh()->status);

        $activity = Activity::query()
            ->where('subject_type', Project::class)
            ->where('subject_id', $project->id)
            ->where('event', 'updated')
            ->latest()
            ->firstOrFail();

        $this->assertTrue($activity->causer->is($admin));
        $this->assertSame('in_progress', $activity->attribute_changes['attributes']['status']);

        $this->expectException(InvalidArgumentException::class);

        ProjectResource::updateStatus($project->fresh(), 'cancelled');
    }

    public function test_accepted_order_project_creation_remains_idempotent(): void
    {
        Mail::fake();

        $order = Order::factory()->create([
            'status' => 'pending',
            'domain' => 'project-idempotent.example',
            'website_type' => 'business',
            'project_description' => 'Build project',
        ]);

        $order->update(['status' => 'accepted']);

        $project = $order->project()->firstOrFail();

        $this->assertSame($order->id, $project->order_id);
        $this->assertSame($order->client_id, $project->client_id);
        $this->assertSame('pending', $project->status);

        $order->update(['status' => 'accepted']);

        $this->assertSame(1, Project::where('order_id', $order->id)->count());
    }

    public function test_linked_project_source_order_cannot_be_cleared_or_replaced_through_edit(): void
    {
        $client = Client::factory()->create();
        $sourceOrder = Order::factory()->create(['client_id' => $client->id]);
        $replacementOrder = Order::factory()->create(['client_id' => $client->id]);
        $project = Project::factory()->create([
            'client_id' => $client->id,
            'order_id' => $sourceOrder->id,
            'status' => 'pending',
        ]);

        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(EditProject::class, ['record' => $project->getKey()])
            ->fillForm([
                'title' => $project->title,
                'client_id' => $client->id,
                'status' => 'pending',
                'progress' => 0,
                'price' => $project->price,
                'deadline' => $project->deadline,
                'description' => $project->description,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($sourceOrder->id, $project->fresh()->order_id);

        foreach ([null, $replacementOrder->id] as $submittedOrderId) {
            try {
                ProjectResource::normalizeFormDataForPersistence([
                    'title' => $project->title,
                    'client_id' => $client->id,
                    'order_id' => $submittedOrderId,
                    'status' => 'pending',
                    'progress' => 0,
                    'price' => $project->price,
                    'deadline' => $project->deadline,
                    'description' => $project->description,
                ], $project->fresh());

                $this->fail('Linked project source order changes should be rejected.');
            } catch (ValidationException) {
                $this->assertSame($sourceOrder->id, $project->fresh()->order_id);
            }
        }

        $this->assertSame($sourceOrder->id, $project->fresh()->order_id);
    }

    public function test_project_source_order_must_belong_to_selected_client(): void
    {
        $selectedClient = Client::factory()->create();
        $otherClient = Client::factory()->create();
        $otherOrder = Order::factory()->create(['client_id' => $otherClient->id]);

        $this->actingAs($this->userWithRole('Admin'));

        Livewire::test(CreateProject::class)
            ->fillForm([
                'title' => 'Mismatched project',
                'client_id' => $selectedClient->id,
                'order_id' => $otherOrder->id,
                'status' => 'pending',
                'progress' => 0,
                'price' => null,
                'deadline' => null,
                'description' => 'Should not persist',
            ])
            ->call('create')
            ->assertHasFormErrors(['order_id']);

        $this->assertDatabaseMissing('projects', [
            'title' => 'Mismatched project',
        ]);
    }

    public function test_order_observer_idempotency_uses_order_id_not_domain_match(): void
    {
        Mail::fake();

        $client = Client::factory()->create();
        $firstOrder = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
            'domain' => 'same-domain.example',
            'website_type' => 'business',
        ]);
        $secondOrder = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
            'domain' => 'same-domain.example',
            'website_type' => 'business',
        ]);

        $firstOrder->update(['status' => 'accepted']);
        $firstOrder->update(['status' => 'accepted']);
        $secondOrder->update(['status' => 'accepted']);

        $this->assertSame(1, Project::where('order_id', $firstOrder->id)->count());
        $this->assertSame(1, Project::where('order_id', $secondOrder->id)->count());
    }

    public function test_linked_projects_are_not_deleted_but_manual_projects_can_be_deleted(): void
    {
        $client = Client::factory()->create();
        $order = Order::factory()->create(['client_id' => $client->id]);
        $linkedProject = Project::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
        ]);
        $manualProject = Project::factory()->create(['order_id' => null]);

        $this->actingAs($this->userWithRole('Admin'));

        $this->assertFalse(ProjectResource::canDelete($linkedProject));
        $this->assertTrue(ProjectResource::canDelete($manualProject));

        Livewire::test(ListProjects::class)
            ->callTableBulkAction('delete', [$linkedProject, $manualProject]);

        $this->assertDatabaseHas('projects', ['id' => $linkedProject->id]);
        $this->assertDatabaseMissing('projects', ['id' => $manualProject->id]);
    }

    public function test_dashboard_project_metric_counts_active_projects(): void
    {
        Project::factory()->create(['status' => 'in_progress']);
        Project::factory()->create(['status' => 'review']);
        Project::factory()->create(['status' => 'completed']);

        $this->assertSame([
            'active_projects' => 1,
            'total_projects' => 3,
        ], app(AdminDashboardOverview::class)->projectMetrics());
    }

    public function test_client_project_routes_are_ownership_scoped(): void
    {
        $owner = $this->userWithRole('Client');
        $other = $this->userWithRole('Client');

        $ownerClient = Client::factory()->create(['user_id' => $owner->id]);
        $otherClient = Client::factory()->create(['user_id' => $other->id]);

        $ownerProject = Project::factory()->create(['client_id' => $ownerClient->id]);
        $otherProject = Project::factory()->create(['client_id' => $otherClient->id]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.project', $ownerProject))
            ->assertOk();

        $this->actingAs($owner)
            ->get(route('client-dashboard.project', $otherProject))
            ->assertNotFound();
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
