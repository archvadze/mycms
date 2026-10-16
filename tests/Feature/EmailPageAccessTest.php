<?php

namespace Tests\Feature;

use App\Filament\Resources\EmailMessageResource;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailPageAccessTest extends TestCase
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
        ] as $name) {
            Role::findOrCreate($name, 'web');
        }
    }

    public function test_admin_and_super_admin_can_open_all_email_pages(): void
    {
        foreach ([AdminAccess::SUPER_ADMIN, AdminAccess::ADMIN] as $role) {
            $this->actingAs($this->user($role));

            foreach (['index', 'compose', 'sent', 'drafts'] as $page) {
                $this->get(EmailMessageResource::getUrl($page))->assertOk();
            }
        }
    }

    public function test_support_can_open_inbox_but_not_outbound_pages(): void
    {
        $this->actingAs($this->user(AdminAccess::SUPPORT));
        $this->get(EmailMessageResource::getUrl('index'))->assertOk();

        foreach (['compose', 'sent', 'drafts'] as $page) {
            $this->get(EmailMessageResource::getUrl($page))->assertForbidden();
        }
    }

    public function test_editor_and_client_cannot_open_email_pages(): void
    {
        foreach ([AdminAccess::EDITOR, AdminAccess::CLIENT] as $role) {
            $this->actingAs($this->user($role));
            foreach (['index', 'compose', 'sent', 'drafts'] as $page) {
                $this->get(EmailMessageResource::getUrl($page))->assertForbidden();
            }
        }
    }

    public function test_blocked_or_unverified_admin_cannot_open_email_pages(): void
    {
        foreach ([
            ['status' => 'blocked', 'email_verified_at' => now()],
            ['status' => 'active', 'email_verified_at' => null],
        ] as $attributes) {
            $this->actingAs($this->user(AdminAccess::ADMIN, $attributes));
            foreach (['index', 'compose', 'sent', 'drafts'] as $page) {
                $this->get(EmailMessageResource::getUrl($page))->assertForbidden();
            }
        }
    }

    private function user(string $role, array $attributes = []): User
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));
        $user->assignRole($role);

        return $user;
    }
}
