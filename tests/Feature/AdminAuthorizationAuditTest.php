<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\EmailMessageResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\TeamResource;
use App\Filament\Resources\TeamResource\Pages\EditTeamMember;
use App\Filament\Resources\TeamResource\Pages\ListTeamMembers;
use App\Filament\Resources\Users\UserResource;
use App\Models\EmailMessage;
use App\Models\EmailThread;
use App\Models\Order;
use App\Models\User;
use App\Support\AdminAccess;
use App\Support\AdminAudit;
use App\Support\AuditLogFormatter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminAuthorizationAuditTest extends TestCase
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

    public function test_direct_resource_access_matches_role_matrix(): void
    {
        $order = Order::factory()->create();
        $thread = EmailThread::create([
            'subject' => 'Authorization',
            'status' => 'open',
            'last_message_at' => now(),
        ]);
        $message = EmailMessage::create([
            'email_thread_id' => $thread->id,
            'direction' => 'inbound',
            'source' => 'resend',
            'message_id' => '<auth-matrix@example.com>',
            'from_email' => 'customer@example.com',
            'to_email' => 'admin@example.com',
            'subject' => 'Authorization',
            'text_body' => 'Body',
            'attachments' => [],
            'metadata' => [],
            'is_read' => false,
            'received_at' => now(),
        ]);
        $activity = Activity::create([
            'description' => 'Audit entry',
            'event' => 'created',
        ]);

        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);
        $this->actingAs($superAdmin);
        $this->get(ManageSettings::getUrl())->assertOk();
        $this->get(UserResource::getUrl())->assertOk();
        $this->get(ActivityLogResource::getUrl('view', ['record' => $activity]))->assertOk();
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk();
        $this->get(EmailMessageResource::getUrl('view', ['record' => $message]))->assertOk();
        $this->get(ServiceResource::getUrl())->assertOk();

        $admin = $this->adminUser(AdminAccess::ADMIN);
        $this->actingAs($admin);
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertOk();
        $this->get(EmailMessageResource::getUrl())->assertOk();
        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(ManageSettings::getUrl())->assertForbidden();
        $this->get(ActivityLogResource::getUrl())->assertForbidden();
        $this->get(ServiceResource::getUrl())->assertForbidden();

        $editor = $this->adminUser(AdminAccess::EDITOR);
        $this->actingAs($editor);
        $this->get(ServiceResource::getUrl())->assertOk();
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertForbidden();
        $this->get(EmailMessageResource::getUrl())->assertForbidden();
        $this->get(ManageSettings::getUrl())->assertForbidden();
        $this->get(ActivityLogResource::getUrl())->assertForbidden();

        $support = $this->adminUser(AdminAccess::SUPPORT);
        $this->actingAs($support);
        $this->get(EmailMessageResource::getUrl())->assertOk();
        $this->get(ServiceResource::getUrl())->assertForbidden();
        $this->get(OrderResource::getUrl('view', ['record' => $order]))->assertForbidden();
        $this->get(UserResource::getUrl())->assertForbidden();
        $this->get(ManageSettings::getUrl())->assertForbidden();
        $this->get(ActivityLogResource::getUrl())->assertForbidden();
    }

    public function test_client_and_blocked_users_cannot_access_filament_panel(): void
    {
        $client = $this->adminUser(AdminAccess::CLIENT);

        $this->actingAs($client)->get('/manage')->assertForbidden();

        $blockedAdmin = $this->adminUser(AdminAccess::ADMIN, 'blocked');

        $this->actingAs($blockedAdmin)->get('/manage')->assertForbidden();
    }

    public function test_user_administration_is_super_admin_only_and_self_delete_is_blocked(): void
    {
        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);
        $admin = $this->adminUser(AdminAccess::ADMIN);

        $this->actingAs($admin);

        $this->assertFalse(UserResource::canViewAny());
        $this->assertFalse(UserResource::canEdit($superAdmin));
        $this->assertSame([], AdminAccess::assignableRoles($admin));

        $this->actingAs($superAdmin);

        $this->assertTrue(UserResource::canViewAny());
        $this->assertTrue(UserResource::canEdit($admin));
        $this->assertFalse(UserResource::canDelete($superAdmin));
        $this->assertContains(AdminAccess::SUPER_ADMIN, AdminAccess::assignableRoles($superAdmin));
    }

    public function test_super_admin_can_manage_another_team_member_through_team_resource(): void
    {
        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);
        $editor = $this->adminUser(AdminAccess::EDITOR);

        $this->actingAs($superAdmin);

        Livewire::test(EditTeamMember::class, ['record' => $editor->getKey()])
            ->fillForm([
                'name' => 'Updated Editor',
                'email' => $editor->email,
                'status' => 'active',
                'roles' => AdminAccess::ADMIN,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $editor->refresh();

        $this->assertSame('Updated Editor', $editor->name);
        $this->assertTrue($editor->hasRole(AdminAccess::ADMIN));
        $this->assertFalse($editor->hasRole(AdminAccess::EDITOR));
    }

    public function test_super_admin_cannot_remove_own_role_through_team_resource(): void
    {
        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);

        $this->actingAs($superAdmin);

        Livewire::test(EditTeamMember::class, ['record' => $superAdmin->getKey()])
            ->fillForm([
                'name' => $superAdmin->name,
                'email' => $superAdmin->email,
                'status' => 'active',
                'roles' => AdminAccess::ADMIN,
            ])
            ->call('save')
            ->assertHasFormErrors(['roles']);

        $this->assertTrue($superAdmin->refresh()->hasRole(AdminAccess::SUPER_ADMIN));
    }

    public function test_team_resource_blocks_individual_self_delete(): void
    {
        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);
        $admin = $this->adminUser(AdminAccess::ADMIN);

        $this->actingAs($superAdmin);

        $this->assertFalse(TeamResource::canDelete($superAdmin));
        $this->assertTrue(TeamResource::canDelete($admin));
    }

    public function test_team_resource_bulk_delete_cannot_delete_authenticated_super_admin(): void
    {
        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);
        $admin = $this->adminUser(AdminAccess::ADMIN);

        $this->actingAs($superAdmin);

        Livewire::test(ListTeamMembers::class)
            ->callTableBulkAction('delete', [$superAdmin, $admin]);

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
        $this->assertDatabaseMissing('users', ['id' => $admin->id]);
    }

    public function test_lower_privileged_roles_cannot_access_team_resource_directly(): void
    {
        foreach ([AdminAccess::ADMIN, AdminAccess::EDITOR, AdminAccess::SUPPORT] as $role) {
            $this->actingAs($this->adminUser($role));

            $this->get(TeamResource::getUrl())->assertForbidden();
        }
    }

    public function test_role_change_audit_records_actor_and_role_delta(): void
    {
        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);
        $target = $this->adminUser(AdminAccess::EDITOR);

        $this->actingAs($superAdmin);

        AdminAudit::logRoleChange(
            $target,
            [AdminAccess::EDITOR],
            [AdminAccess::ADMIN]
        );

        $activity = Activity::query()
            ->where('event', 'roles_updated')
            ->firstOrFail();

        $this->assertTrue($activity->causer->is($superAdmin));
        $this->assertTrue($activity->subject->is($target));
        $this->assertSame([AdminAccess::EDITOR], $activity->properties['old_roles']);
        $this->assertSame([AdminAccess::ADMIN], $activity->properties['new_roles']);
    }

    public function test_audit_formatter_redacts_sensitive_properties(): void
    {
        $activity = Activity::create([
            'description' => 'Sensitive change',
            'event' => 'updated',
            'properties' => [
                'attributes' => [
                    'site_name' => 'Public name',
                    'resend_api_key' => 'secret-value',
                    'password' => 'password-value',
                ],
            ],
        ]);

        $properties = AuditLogFormatter::sanitizedProperties($activity);

        $this->assertSame('Public name', $properties['attributes']['site_name']);
        $this->assertSame('[redacted]', $properties['attributes']['resend_api_key']);
        $this->assertSame('[redacted]', $properties['attributes']['password']);
    }

    public function test_setting_audit_does_not_store_sensitive_values(): void
    {
        $superAdmin = $this->adminUser(AdminAccess::SUPER_ADMIN);

        $this->actingAs($superAdmin);

        AdminAudit::logSettingChange('seo_default_description', 'Old', 'New');
        AdminAudit::logSettingChange('resend_api_key', 'old-secret', 'new-secret');

        $safe = Activity::query()
            ->where('description', 'Site setting seo_default_description changed')
            ->firstOrFail();

        $this->assertSame('Old', $safe->properties['old']);
        $this->assertSame('New', $safe->properties['new']);

        $sensitive = Activity::query()
            ->where('description', 'Site setting resend_api_key changed')
            ->firstOrFail();

        $this->assertSame('resend_api_key', $sensitive->properties['setting_key']);
        $this->assertArrayNotHasKey('old', $sensitive->properties->toArray());
        $this->assertArrayNotHasKey('new', $sensitive->properties->toArray());
    }

    private function adminUser(string $role, string $status = 'active'): User
    {
        $user = User::factory()->create([
            'status' => $status,
            'email_verified_at' => now(),
        ]);

        $user->assignRole($role);

        return $user;
    }
}
