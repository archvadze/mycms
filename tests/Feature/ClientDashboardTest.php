<?php
namespace Tests\Feature;

use App\Models\Client;
use App\Models\DigitalProduct;
use App\Models\DigitalProductVersion;
use App\Models\Order;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\ProjectMessage;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
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
        [$user] = $this->clientUser();

        $response = $this->actingAs($user)->get('/client-dashboard');
        $response->assertStatus(200);
    }

    public function test_dashboard_redirect_keeps_client_route_behavior(): void
    {
        [$user] = $this->clientUser();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('client-dashboard.index'));
    }

    public function test_empty_dashboard_shows_client_next_steps(): void
    {
        [$user] = $this->clientUser();

        $this->actingAs($user)
            ->get(route('client-dashboard.index'))
            ->assertOk()
            ->assertSee('Recent Projects')
            ->assertSee('No projects yet')
            ->assertSee('No recent team messages')
            ->assertSee('No orders yet')
            ->assertSee('No active subscription')
            ->assertSee('No digital purchases yet');
    }

    public function test_client_with_client_record_sees_client_navigation(): void
    {
        [$user] = $this->clientUser();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertSee(route('client-dashboard.index', absolute: false), false)
            ->assertSee($user->name)
            ->assertSee(route('client-dashboard.profile', absolute: false), false)
            ->assertDontSee('Subscription');
    }

    public function test_client_without_client_record_does_not_see_client_portal_navigation(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Client');

        $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->assertDontSee(route('client-dashboard.index', absolute: false), false)
            ->assertDontSee(route('client-dashboard.profile', absolute: false), false);
    }

    public function test_client_without_client_record_cannot_access_dashboard(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Client');

        $this->actingAs($user)
            ->get('/client-dashboard')
            ->assertForbidden();
    }

    public function test_blocked_client_cannot_access_dashboard(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $user->assignRole('Client');
        $user->update(['status' => 'blocked']);

        $response = $this->actingAs($user)->get('/client-dashboard');
        $response->assertRedirect('/login');
    }

    public function test_project_detail_is_ownership_scoped(): void
    {
        [$owner, $ownerClient] = $this->clientUser();
        [$otherUser, $otherClient] = $this->clientUser();

        $ownerProject = Project::factory()->create([
            'client_id' => $ownerClient->id,
            'title' => 'Owner portal project',
        ]);
        $otherProject = Project::factory()->create([
            'client_id' => $otherClient->id,
            'title' => 'Other portal project',
        ]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.project', $ownerProject))
            ->assertOk()
            ->assertSee('Owner portal project')
            ->assertDontSee('Other portal project');

        $this->actingAs($otherUser)
            ->get(route('client-dashboard.project', $ownerProject))
            ->assertNotFound();
    }

    public function test_dashboard_data_is_isolated_to_current_client(): void
    {
        [$owner, $ownerClient] = $this->clientUser();
        [$otherUser, $otherClient] = $this->clientUser();
        $admin = User::factory()->create(['name' => 'Support Agent']);

        $ownerProject = Project::factory()->create([
            'client_id' => $ownerClient->id,
            'title' => 'Alpha client project',
        ]);
        $otherProject = Project::factory()->create([
            'client_id' => $otherClient->id,
            'title' => 'Beta client project',
        ]);

        ProjectMessage::factory()->create([
            'project_id' => $ownerProject->id,
            'sender_id' => $admin->id,
            'message' => 'Alpha-only message',
        ]);
        ProjectMessage::factory()->create([
            'project_id' => $otherProject->id,
            'sender_id' => $admin->id,
            'message' => 'Beta-only message',
        ]);

        Order::factory()->create([
            'client_id' => $ownerClient->id,
            'domain' => 'alpha-client.example',
        ]);
        Order::factory()->create([
            'client_id' => $otherClient->id,
            'domain' => 'beta-client.example',
        ]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.index'))
            ->assertOk()
            ->assertSee('Alpha client project')
            ->assertSee('Alpha-only message')
            ->assertSee('alpha-client.example')
            ->assertDontSee('Beta client project')
            ->assertDontSee('Beta-only message')
            ->assertDontSee('beta-client.example');

        $this->actingAs($otherUser)
            ->get(route('client-dashboard.index'))
            ->assertOk()
            ->assertSee('Beta client project')
            ->assertDontSee('Alpha client project');
    }

    public function test_dashboard_stats_count_owned_records_by_status(): void
    {
        [$owner, $ownerClient] = $this->clientUser();
        [, $otherClient] = $this->clientUser();

        Project::factory()->create(['client_id' => $ownerClient->id, 'status' => 'in_progress']);
        Project::factory()->create(['client_id' => $ownerClient->id, 'status' => 'completed']);
        Project::factory()->create(['client_id' => $otherClient->id, 'status' => 'in_progress']);

        Order::factory()->create(['client_id' => $ownerClient->id, 'status' => 'pending']);
        Order::factory()->create(['client_id' => $ownerClient->id, 'status' => 'accepted']);
        Order::factory()->create(['client_id' => $otherClient->id, 'status' => 'pending']);

        $this->actingAs($owner)
            ->get(route('client-dashboard.index'))
            ->assertOk()
            ->assertSeeInOrder(['Active Projects', '1'])
            ->assertSeeInOrder(['Pending Orders', '1'])
            ->assertSeeInOrder(['Completed', '1'])
            ->assertSeeInOrder(['Total Orders', '2']);
    }

    public function test_dashboard_presents_owned_financial_context_without_internal_identifiers(): void
    {
        [$owner, $ownerClient] = $this->clientUser();
        [$otherUser] = $this->clientUser();

        $order = Order::factory()->create([
            'client_id' => $ownerClient->id,
            'domain' => 'owned-order.example',
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $plan = SubscriptionPlan::create([
            'name' => 'Care Plan',
            'slug' => 'care-plan',
            'description' => 'Ongoing support',
            'price' => 49,
            'currency' => 'EUR',
            'billing_cycle' => 'monthly',
            'features' => ['Priority support'],
            'is_active' => true,
            'sort' => 1,
        ]);

        Subscription::create([
            'user_id' => $owner->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
            'next_invoice_at' => now()->addMonth(),
        ]);

        $product = DigitalProduct::create([
            'name' => 'Owned Toolkit',
            'slug' => 'owned-toolkit',
            'description' => 'A useful product',
            'category' => 'tools',
            'price' => 29,
            'is_published' => true,
            'user_id' => $owner->id,
        ]);
        $version = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/toolkit.zip',
            'is_active' => true,
        ]);
        $purchase = Purchase::create([
            'user_id' => $owner->id,
            'digital_product_version_id' => $version->id,
            'transaction_id' => 'txn-secret-owned',
            'amount' => 29,
            'download_limit' => 3,
            'download_expires_at' => now()->addYear(),
        ]);

        $otherPurchase = $this->purchaseFor($otherUser, [
            'product_name' => 'Other Client Toolkit',
            'transaction_id' => 'txn-secret-other',
        ]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.index'))
            ->assertOk()
            ->assertSee('owned-order.example')
            ->assertSee('Care Plan')
            ->assertSee('Owned Toolkit')
            ->assertSee('3 downloads remaining')
            ->assertDontSee('Other Client Toolkit')
            ->assertDontSee($purchase->transaction_id)
            ->assertDontSee($purchase->license_key)
            ->assertDontSee($otherPurchase->transaction_id);

        $this->assertSame($order->id, $order->fresh()->id);
    }

    public function test_client_message_creation_uses_owned_project_and_authenticated_sender(): void
    {
        [$owner, $ownerClient] = $this->clientUser();
        [$otherUser, $otherClient] = $this->clientUser();
        $ownerProject = Project::factory()->create(['client_id' => $ownerClient->id]);
        $otherProject = Project::factory()->create(['client_id' => $otherClient->id]);

        $this->actingAs($owner)
            ->post(route('client-dashboard.send-message', $ownerProject), [
                'project_id' => $otherProject->id,
                'sender_id' => $otherUser->id,
                'message' => 'Authenticated sender only',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('project_messages', [
            'project_id' => $ownerProject->id,
            'sender_id' => $owner->id,
            'message' => 'Authenticated sender only',
        ]);
        $this->assertDatabaseMissing('project_messages', [
            'project_id' => $otherProject->id,
            'message' => 'Authenticated sender only',
        ]);

        $this->actingAs($owner)
            ->post(route('client-dashboard.send-message', $otherProject), [
                'message' => 'Cross-client message',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('client-dashboard.send-message', $otherProject), [
                'message' => '',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->from(route('client-dashboard.project', $ownerProject))
            ->post(route('client-dashboard.send-message', $ownerProject), [
                'message' => '',
            ])
            ->assertRedirect(route('client-dashboard.project', $ownerProject))
            ->assertSessionHasErrors('message');
    }

    public function test_project_upload_is_owned_private_and_validated(): void
    {
        Storage::fake('local');

        [$owner, $ownerClient] = $this->clientUser();
        [, $otherClient] = $this->clientUser();
        $ownerProject = Project::factory()->create(['client_id' => $ownerClient->id]);
        $otherProject = Project::factory()->create(['client_id' => $otherClient->id]);

        $this->actingAs($owner)
            ->post(route('client-dashboard.upload-file', $ownerProject), [
                'project_id' => $otherProject->id,
                'uploaded_by' => User::factory()->create()->id,
                'file_path' => '../escape.php',
                'file' => UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect();

        $file = ProjectFile::query()->firstOrFail();

        $this->assertSame($ownerProject->id, $file->project_id);
        $this->assertSame($owner->id, $file->uploaded_by);
        $this->assertStringStartsWith("project-files/{$ownerProject->id}/", $file->file_path);
        Storage::disk('local')->assertExists($file->file_path);

        $this->actingAs($owner)
            ->post(route('client-dashboard.upload-file', $otherProject), [
                'file' => UploadedFile::fake()->create('other.pdf', 10, 'application/pdf'),
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('client-dashboard.upload-file', $otherProject), [
                'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->from(route('client-dashboard.project', $ownerProject))
            ->post(route('client-dashboard.upload-file', $ownerProject), [
                'file' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
            ])
            ->assertRedirect(route('client-dashboard.project', $ownerProject))
            ->assertSessionHasErrors('file');

        $this->actingAs($owner)
            ->from(route('client-dashboard.project', $ownerProject))
            ->post(route('client-dashboard.upload-file', $ownerProject), [
                'file' => UploadedFile::fake()->create('large.pdf', 10241, 'application/pdf'),
            ])
            ->assertRedirect(route('client-dashboard.project', $ownerProject))
            ->assertSessionHasErrors('file');
    }

    public function test_project_messages_are_paginated_and_scoped_to_owned_project(): void
    {
        [$owner, $ownerClient] = $this->clientUser();
        [, $otherClient] = $this->clientUser();
        $sender = User::factory()->create(['name' => 'Support Agent']);
        $ownerProject = Project::factory()->create(['client_id' => $ownerClient->id]);
        $otherProject = Project::factory()->create(['client_id' => $otherClient->id]);

        foreach (range(1, 55) as $number) {
            ProjectMessage::factory()->create([
                'project_id' => $ownerProject->id,
                'sender_id' => $sender->id,
                'message' => "Owner message {$number}",
                'created_at' => now()->addMinutes($number),
                'updated_at' => now()->addMinutes($number),
            ]);
        }

        ProjectMessage::factory()->create([
            'project_id' => $otherProject->id,
            'sender_id' => $sender->id,
            'message' => 'Other client hidden message',
            'created_at' => now()->addHour(),
            'updated_at' => now()->addHour(),
        ]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.project', $ownerProject))
            ->assertOk()
            ->assertSee('Owner message 1')
            ->assertSee('Owner message 50')
            ->assertDontSee('Owner message 55')
            ->assertDontSee('Other client hidden message');

        $this->actingAs($owner)
            ->get(route('client-dashboard.project', [
                'id' => $ownerProject->id,
                'messages_page' => 2,
            ]))
            ->assertOk()
            ->assertSee('Owner message 55')
            ->assertDontSee('Other client hidden message');
    }

    public function test_project_file_list_uses_safe_names_and_empty_state(): void
    {
        Storage::fake('local');

        [$owner, $ownerClient] = $this->clientUser();
        $project = Project::factory()->create(['client_id' => $ownerClient->id]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.project', $project))
            ->assertOk()
            ->assertSee('No files yet');

        ProjectFile::create([
            'project_id' => $project->id,
            'file_path' => 'project-files/'.$project->id.'/client-brief.pdf',
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.project', $project))
            ->assertOk()
            ->assertSee('client-brief.pdf')
            ->assertDontSee('project-files/'.$project->id);
    }

    public function test_project_file_download_is_project_and_owner_scoped(): void
    {
        Storage::fake('local');

        [$owner, $ownerClient] = $this->clientUser();
        [$otherUser, $otherClient] = $this->clientUser();
        $ownerProject = Project::factory()->create(['client_id' => $ownerClient->id]);
        $otherProject = Project::factory()->create(['client_id' => $otherClient->id]);

        Storage::disk('local')->put('project-files/owner/report.pdf', 'private report');
        Storage::disk('local')->put('project-files/other/report.pdf', 'other report');

        $ownerFile = ProjectFile::create([
            'project_id' => $ownerProject->id,
            'file_path' => 'project-files/owner/report.pdf',
            'uploaded_by' => $owner->id,
        ]);
        $otherFile = ProjectFile::create([
            'project_id' => $otherProject->id,
            'file_path' => 'project-files/other/report.pdf',
            'uploaded_by' => $otherUser->id,
        ]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.download-file', [$ownerProject, $ownerFile]))
            ->assertOk()
            ->assertDownload('report.pdf');

        $this->actingAs($otherUser)
            ->get(route('client-dashboard.download-file', [$ownerProject, $ownerFile]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('client-dashboard.download-file', [$ownerProject, $otherFile]))
            ->assertNotFound();
    }

    public function test_project_file_download_supports_legacy_public_files_and_missing_files_404(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner, $ownerClient] = $this->clientUser();
        $project = Project::factory()->create(['client_id' => $ownerClient->id]);

        Storage::disk('public')->put('project-files/legacy.pdf', 'legacy report');
        $legacyFile = ProjectFile::create([
            'project_id' => $project->id,
            'file_path' => 'project-files/legacy.pdf',
            'uploaded_by' => $owner->id,
        ]);
        $missingFile = ProjectFile::create([
            'project_id' => $project->id,
            'file_path' => 'project-files/missing.pdf',
            'uploaded_by' => $owner->id,
        ]);

        $this->actingAs($owner)
            ->get(route('client-dashboard.download-file', [$project, $legacyFile]))
            ->assertOk()
            ->assertDownload('legacy.pdf');

        $this->actingAs($owner)
            ->get(route('client-dashboard.download-file', [$project, $missingFile]))
            ->assertNotFound();
    }

    public function test_blocked_client_cannot_access_direct_project_endpoints(): void
    {
        Storage::fake('local');

        [$user, $client] = $this->clientUser(['status' => 'blocked']);
        $project = Project::factory()->create(['client_id' => $client->id]);
        $file = ProjectFile::create([
            'project_id' => $project->id,
            'file_path' => 'project-files/blocked.pdf',
            'uploaded_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->get(route('client-dashboard.project', $project))
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->post(route('client-dashboard.send-message', $project), ['message' => 'blocked'])
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->post(route('client-dashboard.upload-file', $project), [
                'file' => UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf'),
            ])
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->get(route('client-dashboard.download-file', [$project, $file]))
            ->assertRedirect('/login');
    }

    private function purchaseFor(User $user, array $attributes = []): Purchase
    {
        $product = DigitalProduct::create([
            'name' => $attributes['product_name'] ?? 'Client Product',
            'slug' => Str::slug($attributes['product_name'] ?? 'Client Product').'-'.Str::random(6),
            'description' => 'Downloadable product',
            'category' => 'tools',
            'price' => $attributes['amount'] ?? 49,
            'is_published' => true,
            'user_id' => $user->id,
        ]);

        $version = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => $attributes['version_number'] ?? '1.0',
            'file_path' => $attributes['file_path'] ?? 'digital-products/product.zip',
            'is_active' => true,
        ]);

        return Purchase::create([
            'user_id' => $user->id,
            'digital_product_version_id' => $version->id,
            'transaction_id' => $attributes['transaction_id'] ?? 'txn-'.Str::random(8),
            'amount' => $attributes['amount'] ?? 49,
            'download_limit' => $attributes['download_limit'] ?? 5,
            'download_expires_at' => $attributes['download_expires_at'] ?? now()->addYear(),
        ]);
    }

    private function clientUser(array $attributes = []): array
    {
        $user = User::factory()->create(array_merge([
            'status' => 'active',
            'email_verified_at' => now(),
        ], $attributes));
        $user->assignRole('Client');

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return [$user, $client];
    }
}
