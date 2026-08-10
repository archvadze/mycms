<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\BlogCommentResource;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\DigitalProductResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\PurchaseResource\Pages\ListPurchases;
use App\Filament\Resources\SubscriptionPlanResource;
use App\Filament\Resources\SubscriptionResource;
use App\Models\Client;
use App\Models\DigitalProduct;
use App\Models\DigitalProductVersion;
use App\Models\Order;
use App\Models\Purchase;
use App\Models\User;
use App\Support\AdminAccess;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminUxPolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([AdminAccess::SUPER_ADMIN, AdminAccess::ADMIN, AdminAccess::EDITOR, AdminAccess::SUPPORT, AdminAccess::CLIENT] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_navigation_groups_and_labels_are_operationally_consistent(): void
    {
        $this->assertSame('Projects', ProjectResource::getNavigationLabel());
        $this->assertSame('Content', PageResource::getNavigationGroup());
        $this->assertSame('Content', DigitalProductResource::getNavigationGroup());
        $this->assertSame('Support', ContactMessageResource::getNavigationGroup());
        $this->assertSame('Support', BlogCommentResource::getNavigationGroup());
        $this->assertSame('Operations', SubscriptionResource::getNavigationGroup());
        $this->assertSame('Subscription Plans', SubscriptionPlanResource::getNavigationLabel());
        $this->assertSame('System', ActivityLogResource::getNavigationGroup());
    }

    public function test_order_table_filters_by_payment_status_and_client(): void
    {
        $admin = $this->userWithRole(AdminAccess::ADMIN);
        $client = Client::factory()->create(['name' => 'Filtered Client']);
        $matching = Order::factory()->create([
            'client_id' => $client->id,
            'payment_status' => 'paid',
        ]);
        $wrongPayment = Order::factory()->create([
            'client_id' => $client->id,
            'payment_status' => 'unpaid',
        ]);
        $wrongClient = Order::factory()->create([
            'payment_status' => 'paid',
        ]);

        $this->actingAs($admin);

        Livewire::test(ListOrders::class)
            ->filterTable('payment_status', 'paid')
            ->filterTable('client_id', $client->id)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$wrongPayment, $wrongClient]);
    }

    public function test_purchase_table_filters_by_customer_and_download_state(): void
    {
        $admin = $this->userWithRole(AdminAccess::ADMIN);
        $customer = User::factory()->create(['name' => 'Purchaser']);
        $otherCustomer = User::factory()->create(['name' => 'Other Purchaser']);
        $version = DigitalProductVersion::create([
            'digital_product_id' => DigitalProduct::create([
                'name' => 'Filter Product',
                'slug' => 'filter-product',
                'description' => 'Filter product',
                'price' => 10,
                'is_published' => true,
                'user_id' => $admin->id,
            ])->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/filter.zip',
            'is_active' => true,
        ]);
        $available = Purchase::create([
            'user_id' => $customer->id,
            'digital_product_version_id' => $version->id,
            'transaction_id' => 'txn-available',
            'amount' => 10,
            'download_limit' => 3,
            'download_expires_at' => now()->addDay(),
        ]);
        $expired = Purchase::create([
            'user_id' => $customer->id,
            'digital_product_version_id' => $version->id,
            'transaction_id' => 'txn-expired',
            'amount' => 10,
            'download_limit' => 3,
            'download_expires_at' => now()->subDay(),
        ]);
        $other = Purchase::create([
            'user_id' => $otherCustomer->id,
            'digital_product_version_id' => $version->id,
            'transaction_id' => 'txn-other',
            'amount' => 10,
            'download_limit' => 3,
            'download_expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin);

        Livewire::test(ListPurchases::class)
            ->filterTable('user_id', $customer->id)
            ->filterTable('download_state', ['state' => 'available'])
            ->assertCanSeeTableRecords([$available])
            ->assertCanNotSeeTableRecords([$expired, $other]);
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
