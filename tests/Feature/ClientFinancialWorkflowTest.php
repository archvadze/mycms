<?php

namespace Tests\Feature;

use App\Http\Controllers\PaymentController;
use App\Models\Client;
use App\Models\DigitalProduct;
use App\Models\DigitalProductVersion;
use App\Models\Order;
use App\Models\Project;
use App\Models\Purchase;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Spatie\Permission\Models\Role;
use Srmklive\PayPal\Services\PayPal as PayPalClient;
use Tests\TestCase;

class ClientFinancialWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Client', 'guard_name' => 'web']);
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
    }

    public function test_order_success_and_payment_cancel_are_client_owned(): void
    {
        [$owner, $ownerClient] = $this->clientUser();
        [$otherUser, $otherClient] = $this->clientUser();

        $ownerOrder = Order::factory()->create([
            'client_id' => $ownerClient->id,
            'domain' => 'owner-order.example',
        ]);
        $otherOrder = Order::factory()->create([
            'client_id' => $otherClient->id,
            'domain' => 'other-order.example',
        ]);

        $this->actingAs($owner)
            ->get(route('order.success', $ownerOrder))
            ->assertOk()
            ->assertSee('owner-order.example')
            ->assertDontSee('other-order.example');

        $this->actingAs($owner)
            ->get(route('order.success', $otherOrder))
            ->assertNotFound();

        $this->actingAs($owner)
            ->get(route('payment.cancel', $otherOrder))
            ->assertNotFound();

        $this->actingAs($otherUser)
            ->get(route('order.success', $ownerOrder))
            ->assertNotFound();
    }

    public function test_blocked_client_cannot_access_order_payment_purchase_or_subscription_routes(): void
    {
        [$user, $client] = $this->clientUser(['status' => 'blocked']);
        $order = Order::factory()->create(['client_id' => $client->id]);
        $purchase = $this->purchaseFor($user);

        $this->actingAs($user)
            ->get(route('order.success', $order))
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->get(route('payment.cancel', $order))
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->post(route('purchase.download', $purchase))
            ->assertRedirect('/login');

        $this->actingAs($user)
            ->post(route('subscription.cancel'))
            ->assertRedirect('/login');
    }

    public function test_purchase_download_requires_owned_valid_purchase_and_existing_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner] = $this->clientUser();
        [$otherUser] = $this->clientUser();

        $purchase = $this->purchaseFor($owner, [
            'file_path' => 'digital-products/owned.zip',
            'product_name' => 'Owned Product',
            'version_number' => '1.0',
        ]);
        Storage::disk('public')->put('digital-products/owned.zip', 'owned file');

        $this->actingAs($owner)
            ->post(route('purchase.download', $purchase))
            ->assertOk()
            ->assertDownload('Owned_Product_v1.0.zip');

        $this->assertSame(4, $purchase->fresh()->download_limit);

        $this->actingAs($otherUser)
            ->post(route('purchase.download', $purchase))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('purchase.download', $purchase->id + 999))
            ->assertNotFound();

        $missing = $this->purchaseFor($owner, [
            'file_path' => 'digital-products/missing.zip',
        ]);

        $this->actingAs($owner)
            ->post(route('purchase.download', $missing))
            ->assertNotFound();
    }

    public function test_purchase_download_uses_purchased_version_and_preserves_unpublished_entitlement(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner] = $this->clientUser();
        $product = DigitalProduct::create([
            'name' => 'Historical Product',
            'slug' => 'historical-product',
            'description' => 'Historical entitlement',
            'price' => 99,
            'is_published' => false,
            'user_id' => $owner->id,
        ]);
        $purchasedVersion = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/purchased.zip',
            'is_active' => true,
        ]);
        DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '2.0',
            'file_path' => 'digital-products/not-purchased.zip',
            'is_active' => true,
        ]);
        Storage::disk('public')->put('digital-products/purchased.zip', 'purchased');
        Storage::disk('public')->put('digital-products/not-purchased.zip', 'not purchased');

        $purchase = Purchase::create([
            'user_id' => $owner->id,
            'digital_product_version_id' => $purchasedVersion->id,
            'transaction_id' => 'txn-historical',
            'amount' => 99,
            'download_limit' => 5,
            'download_expires_at' => now()->addYear(),
        ]);

        $this->actingAs($owner)
            ->post(route('purchase.download', $purchase))
            ->assertOk()
            ->assertDownload('Historical_Product_v1.0.zip');
    }

    public function test_ineligible_purchase_downloads_do_not_deliver_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner] = $this->clientUser();
        $limited = $this->purchaseFor($owner, [
            'file_path' => 'digital-products/limited.zip',
            'download_limit' => 0,
        ]);
        $expired = $this->purchaseFor($owner, [
            'file_path' => 'digital-products/expired.zip',
            'download_expires_at' => now()->subDay(),
        ]);
        Storage::disk('public')->put('digital-products/limited.zip', 'limited');
        Storage::disk('public')->put('digital-products/expired.zip', 'expired');

        $this->actingAs($owner)
            ->post(route('purchase.download', $limited))
            ->assertRedirect()
            ->assertSessionHas('error', 'Download limit reached.');

        $this->actingAs($owner)
            ->post(route('purchase.download', $expired))
            ->assertRedirect()
            ->assertSessionHas('error', 'Download access has expired.');
    }

    public function test_purchase_download_get_does_not_mutate_or_download(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner] = $this->clientUser();
        $purchase = $this->purchaseFor($owner, [
            'file_path' => 'digital-products/get-blocked.zip',
            'download_limit' => 1,
        ]);
        Storage::disk('public')->put('digital-products/get-blocked.zip', 'file');

        $this->actingAs($owner)
            ->get(route('purchase.download', $purchase))
            ->assertStatus(405);

        $this->assertSame(1, $purchase->fresh()->download_limit);
        $this->assertDatabaseCount('download_logs', 0);
    }

    public function test_purchase_download_limit_does_not_go_negative(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner] = $this->clientUser();
        $purchase = $this->purchaseFor($owner, [
            'file_path' => 'digital-products/last.zip',
            'download_limit' => 1,
        ]);
        Storage::disk('public')->put('digital-products/last.zip', 'file');

        $this->actingAs($owner)
            ->post(route('purchase.download', $purchase))
            ->assertOk();

        $this->actingAs($owner)
            ->post(route('purchase.download', $purchase))
            ->assertRedirect()
            ->assertSessionHas('error', 'Download limit reached.');

        $this->assertSame(0, $purchase->fresh()->download_limit);
        $this->assertDatabaseCount('download_logs', 1);
    }

    public function test_payment_create_failure_uses_generic_browser_message(): void
    {
        Mail::fake();

        [$owner, $client] = $this->clientUser();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
        ]);

        $this->fakePayPalProvider(createResponse: [
            'id' => 'PAYPAL-ORDER-ID',
            'status' => 'FAILED',
            'links' => [
                ['rel' => 'approve', 'href' => 'https://paypal.example/sensitive-approval-url'],
            ],
            'debug_id' => 'sensitive-debug-id',
        ]);

        $response = $this->actingAs($owner)
            ->get(route('payment.create', $order));

        $response->assertRedirect()
            ->assertSessionHas('error', 'Payment could not be started. Please try again.');

        $this->assertStringNotContainsString(
            'sensitive-approval-url',
            (string) session('error')
        );
        $this->assertStringNotContainsString('debug_id', (string) session('error'));
    }

    public function test_payment_capture_binding_requires_matching_reference_amount_and_currency(): void
    {
        Mail::fake();

        [$owner, $client] = $this->clientUser();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'price_estimate' => 125,
        ]);

        $this->assertTrue(PaymentController::captureMatchesOrder(
            $this->captureResponse($order, '125.00', 'USD', 'ORDER-' . $order->id),
            $order
        ));

        $this->assertFalse(PaymentController::captureMatchesOrder(
            $this->captureResponse($order, '125.00', 'USD', 'ORDER-999'),
            $order
        ));

        $this->assertFalse(PaymentController::captureMatchesOrder(
            $this->captureResponse($order, '124.00', 'USD', 'ORDER-' . $order->id),
            $order
        ));

        $this->assertFalse(PaymentController::captureMatchesOrder(
            $this->captureResponse($order, '125.00', 'EUR', 'ORDER-' . $order->id),
            $order
        ));

        $this->fakePayPalProvider(captureResponse: $this->captureResponse(
            $order,
            '125.00',
            'USD',
            'ORDER-' . $order->id
        ));

        $this->actingAs($owner)
            ->get(route('payment.success', [
                'orderId' => $order->id,
                'token' => 'paypal-token',
            ]))
            ->assertRedirect(route('order.success', $order));

        $order->refresh();

        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('accepted', $order->status);
        $this->assertSame('CAPTURE-ID', $order->payment_id);
    }

    public function test_payment_capture_mismatch_does_not_mark_order_paid(): void
    {
        Mail::fake();

        [$owner, $client] = $this->clientUser();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'price_estimate' => 125,
        ]);

        $this->fakePayPalProvider(captureResponse: $this->captureResponse(
            $order,
            '124.00',
            'USD',
            'ORDER-' . $order->id
        ));

        $this->actingAs($owner)
            ->get(route('payment.success', [
                'orderId' => $order->id,
                'token' => 'paypal-token',
            ]))
            ->assertRedirect(route('payment.cancel', $order));

        $order->refresh();

        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('pending', $order->status);
        $this->assertNull($order->payment_id);
    }

    public function test_already_paid_order_success_does_not_repeat_capture_or_project_side_effects(): void
    {
        Mail::fake();

        [$owner, $client] = $this->clientUser();
        $order = Order::factory()->create([
            'client_id' => $client->id,
            'status' => 'accepted',
            'payment_status' => 'paid',
            'payment_id' => 'EXISTING-CAPTURE',
        ]);
        Project::factory()->create([
            'client_id' => $client->id,
            'order_id' => $order->id,
        ]);

        $this->fakePayPalProvider(captureResponse: null, expectCapture: false);

        $this->actingAs($owner)
            ->get(route('payment.success', [
                'orderId' => $order->id,
                'token' => 'paypal-token',
            ]))
            ->assertRedirect(route('order.success', $order));

        $this->assertSame('EXISTING-CAPTURE', $order->fresh()->payment_id);
        $this->assertSame(1, Project::where('order_id', $order->id)->count());
    }

    public function test_checkout_success_rejects_version_that_does_not_belong_to_route_product_before_capture(): void
    {
        [$user] = $this->clientUser();
        $routeProduct = $this->productFor($user, [
            'name' => 'Route Product',
            'slug' => 'route-product',
        ]);
        $otherProduct = $this->productFor($user, [
            'name' => 'Other Product',
            'slug' => 'other-product',
        ]);
        $otherVersion = DigitalProductVersion::create([
            'digital_product_id' => $otherProduct->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/other.zip',
            'is_active' => true,
        ]);

        $this->actingAs($user)
            ->withSession(['shop_version_id' => $otherVersion->id])
            ->get(route('shop.checkout.success', [
                'slug' => $routeProduct->slug,
                'token' => 'paypal-token',
            ]))
            ->assertNotFound();

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_subscription_request_and_cancel_are_current_user_owned(): void
    {
        Mail::fake();

        [$owner] = $this->clientUser();
        [$otherUser] = $this->clientUser();
        $plan = SubscriptionPlan::create([
            'name' => 'Support',
            'slug' => 'support',
            'description' => 'Support plan',
            'price' => 25,
            'billing_cycle' => 'monthly',
            'features' => ['Support'],
            'is_active' => true,
            'sort' => 1,
        ]);
        $inactivePlan = SubscriptionPlan::create([
            'name' => 'Inactive',
            'slug' => 'inactive',
            'description' => 'Inactive plan',
            'price' => 10,
            'billing_cycle' => 'monthly',
            'is_active' => false,
            'sort' => 2,
        ]);
        $otherSubscription = Subscription::create([
            'user_id' => $otherUser->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->actingAs($owner)
            ->get(route('subscription.plans'))
            ->assertOk()
            ->assertDontSee('You are currently on the');

        $this->actingAs($owner)
            ->post(route('subscription.request', $inactivePlan))
            ->assertNotFound();

        $this->actingAs($owner)
            ->post(route('subscription.request', $plan))
            ->assertRedirect(route('client-dashboard.index'));

        $this->assertDatabaseHas('subscriptions', [
            'user_id' => $owner->id,
            'subscription_plan_id' => $plan->id,
            'status' => 'pending',
        ]);
        $this->assertFalse($otherSubscription->fresh()->cancel_requested);

        $this->actingAs($owner)
            ->post(route('subscription.request', $plan))
            ->assertRedirect();

        $this->assertSame(1, Subscription::where('user_id', $owner->id)->count());
    }

    public function test_client_order_store_ignores_spoofed_financial_and_identity_fields(): void
    {
        [$owner, $client] = $this->clientUser();

        $service = \App\Models\Service::create([
            'name' => 'Secure Service',
            'slug' => 'secure-service',
            'description' => 'Secure',
            'base_price' => 250,
            'status' => true,
        ]);

        $this->actingAs($owner)
            ->post(route('order.store'), [
                'client_name' => 'Spoofed Name',
                'email' => 'spoof@example.com',
                'phone' => '000',
                'domain' => 'secure-order.example',
                'website_type' => 'business',
                'timeline' => 'standard',
                'budget_range' => 'low',
                'services' => [$service->id],
                'features' => [],
                'project_description' => 'Build it',
                'additional_requirements' => null,
                'client_id' => 999,
                'price_estimate' => 1,
                'status' => 'accepted',
                'payment_status' => 'paid',
                'payment_id' => 'client-supplied',
                'payment_method' => 'client-supplied',
                'paid_at' => now()->toDateTimeString(),
            ])
            ->assertRedirect();

        $order = Order::where('domain', 'secure-order.example')->firstOrFail();

        $this->assertSame($client->id, $order->client_id);
        $this->assertSame($client->name, $order->client_name);
        $this->assertSame($client->email, $order->email);
        $this->assertSame(250.0, (float) $order->price_estimate);
        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertNull($order->payment_id);
        $this->assertNull($order->payment_method);
        $this->assertNull($order->paid_at);
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

    private function fakePayPalProvider(
        ?array $createResponse = null,
        ?array $captureResponse = null,
        bool $expectCapture = true
    ): void {
        $provider = Mockery::mock(PayPalClient::class);
        $provider->shouldReceive('setApiCredentials')->zeroOrMoreTimes();
        $provider->shouldReceive('getAccessToken')->zeroOrMoreTimes()->andReturn([
            'access_token' => 'test-token',
        ]);
        $provider->shouldReceive('setAccessToken')->zeroOrMoreTimes();

        if ($createResponse !== null) {
            $provider->shouldReceive('createOrder')->once()->andReturn($createResponse);
        }

        if (! $expectCapture) {
            $provider->shouldReceive('capturePaymentOrder')->never();
        } elseif ($captureResponse !== null) {
            $provider->shouldReceive('capturePaymentOrder')->once()->andReturn($captureResponse);
        }

        app()->instance(PayPalClient::class, $provider);
    }

    private function captureResponse(
        Order $order,
        string $amount,
        string $currency,
        string $reference
    ): array {
        return [
            'id' => 'CAPTURE-ID',
            'status' => 'COMPLETED',
            'purchase_units' => [
                [
                    'reference_id' => $reference,
                    'payments' => [
                        'captures' => [
                            [
                                'amount' => [
                                    'currency_code' => $currency,
                                    'value' => $amount,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function productFor(User $user, array $attributes = []): DigitalProduct
    {
        return DigitalProduct::create(array_merge([
            'name' => 'Digital Product',
            'slug' => 'digital-product-' . uniqid(),
            'description' => 'Digital product',
            'price' => 49,
            'is_published' => true,
            'user_id' => $user->id,
        ], $attributes));
    }

    private function purchaseFor(User $user, array $attributes = []): Purchase
    {
        $product = $this->productFor($user, [
            'name' => $attributes['product_name'] ?? 'Download Product',
        ]);
        $version = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => $attributes['version_number'] ?? '1.0',
            'file_path' => $attributes['file_path'] ?? 'digital-products/file.zip',
            'is_active' => true,
        ]);

        return Purchase::create([
            'user_id' => $user->id,
            'digital_product_version_id' => $version->id,
            'transaction_id' => $attributes['transaction_id'] ?? 'txn-' . uniqid(),
            'amount' => $attributes['amount'] ?? $product->price,
            'download_limit' => $attributes['download_limit'] ?? 5,
            'download_expires_at' => $attributes['download_expires_at'] ?? now()->addYear(),
        ]);
    }
}
