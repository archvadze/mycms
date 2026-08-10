<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\ActivityLogs\Pages\ListActivityLogs;
use App\Filament\Resources\GuideResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Guide;
use App\Models\Order;
use App\Models\Project;
use App\Models\Service;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AuditLogFormatter;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FinalAuthorizationRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            AdminAccess::SUPER_ADMIN,
            AdminAccess::ADMIN,
            AdminAccess::EDITOR,
            AdminAccess::SUPPORT,
            AdminAccess::CLIENT,
        ] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_panel_access_requires_allowed_role_active_status_and_verified_email(): void
    {
        foreach ([AdminAccess::SUPER_ADMIN, AdminAccess::ADMIN, AdminAccess::EDITOR, AdminAccess::SUPPORT] as $role) {
            $this->actingAs($this->userWithRole($role))
                ->get('/manage')
                ->assertOk();
        }

        $this->actingAs($this->userWithRole(AdminAccess::CLIENT))
            ->get('/manage')
            ->assertForbidden();

        $this->actingAs($this->userWithRole(AdminAccess::ADMIN, status: 'blocked'))
            ->get('/manage')
            ->assertForbidden();

        $this->actingAs($this->userWithRole(AdminAccess::ADMIN, verified: false))
            ->get('/manage')
            ->assertForbidden();
    }

    public function test_direct_manage_routes_follow_role_matrix(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $project = Project::factory()->create(['status' => 'pending']);
        $service = Service::factory()->create();
        $activity = Activity::create([
            'description' => 'Security event',
            'event' => 'updated',
        ]);

        $this->actingAs($this->userWithRole(AdminAccess::SUPER_ADMIN));
        $this->get(UserResource::getUrl())->assertOk();
        $this->get(ManageSettings::getUrl())->assertOk();
        $this->get(ActivityLogResource::getUrl('view', ['record' => $activity]))->assertOk();
        $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertOk();
        $this->get(ProjectResource::getUrl('edit', ['record' => $project]))->assertOk();
        $this->get(ServiceResource::getUrl('edit', ['record' => $service]))->assertOk();

        $this->actingAs($this->userWithRole(AdminAccess::ADMIN));
        $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertOk();
        $this->get(ProjectResource::getUrl('edit', ['record' => $project]))->assertOk();
        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(ManageSettings::getUrl())->assertForbidden();
        $this->get(ActivityLogResource::getUrl())->assertForbidden();
        $this->get(ServiceResource::getUrl('edit', ['record' => $service]))->assertForbidden();

        $this->actingAs($this->userWithRole(AdminAccess::EDITOR));
        $this->get(ServiceResource::getUrl('edit', ['record' => $service]))->assertOk();
        $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();
        $this->get(ProjectResource::getUrl('edit', ['record' => $project]))->assertForbidden();
        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(ActivityLogResource::getUrl())->assertForbidden();

        $this->actingAs($this->userWithRole(AdminAccess::SUPPORT));
        $this->get(ServiceResource::getUrl('edit', ['record' => $service]))->assertForbidden();
        $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();
        $this->get(ProjectResource::getUrl('edit', ['record' => $project]))->assertForbidden();
        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(ActivityLogResource::getUrl())->assertForbidden();

        $this->actingAs($this->userWithRole(AdminAccess::CLIENT));
        $this->get('/manage')->assertForbidden();
        $this->get(OrderResource::getUrl('edit', ['record' => $order]))->assertForbidden();
    }

    public function test_audit_actor_filter_scopes_to_selected_causer(): void
    {
        $superAdmin = $this->userWithRole(AdminAccess::SUPER_ADMIN);
        $actor = $this->userWithRole(AdminAccess::ADMIN);
        $otherActor = $this->userWithRole(AdminAccess::EDITOR);

        $matched = Activity::create([
            'description' => 'Matched actor',
            'event' => 'updated',
            'causer_type' => User::class,
            'causer_id' => $actor->id,
        ]);
        $unmatched = Activity::create([
            'description' => 'Other actor',
            'event' => 'updated',
            'causer_type' => User::class,
            'causer_id' => $otherActor->id,
        ]);

        $this->actingAs($superAdmin);

        Livewire::test(ListActivityLogs::class)
            ->filterTable('actor', ['causer_id' => $actor->id])
            ->assertCanSeeTableRecords([$matched])
            ->assertCanNotSeeTableRecords([$unmatched]);
    }

    public function test_audit_log_is_read_only_and_nested_sensitive_values_are_redacted(): void
    {
        $activity = Activity::create([
            'description' => 'Sensitive nested update',
            'event' => 'updated',
            'properties' => [
                'settings' => [
                    'public_email' => 'public@example.com',
                    'mail' => [
                        'client_secret' => 'secret-value',
                        'access_token' => 'token-value',
                    ],
                    'webhook' => [
                        'headers' => [
                            'authorization' => 'Bearer secret',
                        ],
                    ],
                ],
            ],
        ]);

        $properties = AuditLogFormatter::sanitizedProperties($activity);

        $this->assertFalse(ActivityLogResource::canCreate());
        $this->assertFalse(ActivityLogResource::canEdit($activity));
        $this->assertFalse(ActivityLogResource::canDelete($activity));
        $this->assertFalse(ActivityLogResource::canDeleteAny());
        $this->assertSame('public@example.com', $properties['settings']['public_email']);
        $this->assertSame('[redacted]', $properties['settings']['mail']['client_secret']);
        $this->assertSame('[redacted]', $properties['settings']['mail']['access_token']);
        $this->assertSame('[redacted]', $properties['settings']['webhook']['headers']['authorization']);
    }

    public function test_custom_workflow_helpers_enforce_role_boundaries_server_side(): void
    {
        $order = Order::factory()->create(['status' => 'pending']);
        $project = Project::factory()->create(['status' => 'pending']);
        $guide = Guide::factory()->create(['published_at' => null]);

        $this->actingAs($this->userWithRole(AdminAccess::EDITOR));
        $this->expectException(AuthorizationException::class);
        OrderResource::updateStatus($order, 'contacted');
    }

    public function test_content_and_project_mutation_helpers_enforce_role_boundaries_server_side(): void
    {
        $project = Project::factory()->create(['status' => 'pending']);
        $editableGuide = Guide::factory()->create(['published_at' => null]);
        $blockedGuide = Guide::factory()->create(['published_at' => null]);

        $this->actingAs($this->userWithRole(AdminAccess::EDITOR));

        GuideResource::setPublished($editableGuide, true);

        $this->assertNotNull($editableGuide->fresh()->published_at);

        $this->actingAs($this->userWithRole(AdminAccess::SUPPORT));

        try {
            ProjectResource::updateStatus($project, 'in_progress');
            $this->fail('Support must not update project status.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('projects', [
                'id' => $project->id,
                'status' => 'pending',
            ]);
        }

        try {
            GuideResource::setPublished($blockedGuide, true);
            $this->fail('Support must not publish content.');
        } catch (AuthorizationException) {
            $this->assertDatabaseHas('guides', [
                'id' => $blockedGuide->id,
                'published_at' => null,
            ]);
        }
    }

    private function userWithRole(string $role, string $status = 'active', bool $verified = true): User
    {
        $user = User::factory()->create([
            'status' => $status,
            'email_verified_at' => $verified ? now() : null,
        ]);

        $user->assignRole($role);

        return $user;
    }
}
