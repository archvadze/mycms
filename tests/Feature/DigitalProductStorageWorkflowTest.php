<?php

namespace Tests\Feature;

use App\Filament\Resources\DigitalProductResource;
use App\Filament\Resources\DigitalProductVersionResource;
use App\Filament\Resources\DigitalProductVersionResource\Pages\CreateDigitalProductVersion;
use App\Models\Client;
use App\Models\DigitalProduct;
use App\Models\DigitalProductVersion;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class DigitalProductStorageWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Client', 'Super Admin'] as $role) {
            Role::create(['name' => $role, 'guard_name' => 'web']);
        }
    }

    public function test_new_digital_product_version_upload_uses_private_local_storage(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $product = $this->productFor($admin);

        $this->actingAs($admin);

        Livewire::test(CreateDigitalProductVersion::class)
            ->fillForm([
                'digital_product_id' => $product->id,
                'version_number' => '1.0.0',
                'changelog' => 'Initial release',
                'is_active' => true,
                'file_path' => UploadedFile::fake()->create('release.zip', 128, 'application/zip'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $version = DigitalProductVersion::query()->firstOrFail();

        $this->assertStringStartsWith("digital-products/{$product->id}/", $version->file_path);
        $this->assertStringEndsWith('.zip', $version->file_path);
        $this->assertStringNotContainsString('release.zip', $version->file_path);
        $this->assertFalse(str_contains($version->file_path, '..'));
        Storage::disk('local')->assertExists($version->file_path);
        Storage::disk('public')->assertMissing($version->file_path);
    }

    public function test_executable_digital_product_version_upload_is_rejected(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $product = $this->productFor($admin);

        $this->actingAs($admin);

        Livewire::test(CreateDigitalProductVersion::class)
            ->fillForm([
                'digital_product_id' => $product->id,
                'version_number' => '1.0.0',
                'is_active' => true,
                'file_path' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file_path']);

        $this->assertDatabaseCount('digital_product_versions', 0);
    }

    public function test_dangerous_original_extension_with_allowed_mime_is_rejected(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $product = $this->productFor($admin);

        $this->actingAs($admin);

        Livewire::test(CreateDigitalProductVersion::class)
            ->fillForm([
                'digital_product_id' => $product->id,
                'version_number' => '1.0.0',
                'is_active' => true,
                'file_path' => UploadedFile::fake()->create('payload.php', 1, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasFormErrors(['file_path']);

        $this->assertDatabaseCount('digital_product_versions', 0);
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_normal_private_pdf_upload_uses_server_derived_extension(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $product = $this->productFor($admin);

        $this->actingAs($admin);

        Livewire::test(CreateDigitalProductVersion::class)
            ->fillForm([
                'digital_product_id' => $product->id,
                'version_number' => '1.0.0',
                'is_active' => true,
                'file_path' => UploadedFile::fake()->create('manual.pdf', 1, 'application/pdf'),
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $version = DigitalProductVersion::query()->firstOrFail();

        $this->assertStringEndsWith('.pdf', $version->file_path);
        $this->assertStringNotContainsString('manual.pdf', $version->file_path);
        Storage::disk('local')->assertExists($version->file_path);
        Storage::disk('public')->assertMissing($version->file_path);
    }

    public function test_purchase_download_prefers_private_file_and_falls_back_to_legacy_public_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner] = $this->clientUser();
        $privatePurchase = $this->purchaseFor($owner, 'digital-products/private.zip');
        Storage::disk('local')->put('digital-products/private.zip', 'private');

        $privateResponse = $this->actingAs($owner)
            ->post(route('purchase.download', $privatePurchase))
            ->assertOk()
            ->assertDownload('Download_Product_v1.0.zip');

        $this->assertSame('private', $privateResponse->streamedContent());

        $legacyPurchase = $this->purchaseFor($owner, 'products/files/legacy.zip');
        Storage::disk('public')->put('products/files/legacy.zip', 'legacy');

        $legacyResponse = $this->actingAs($owner)
            ->post(route('purchase.download', $legacyPurchase))
            ->assertOk()
            ->assertDownload('Download_Product_v1.0.zip');

        $this->assertSame('legacy', $legacyResponse->streamedContent());

        $missingPurchase = $this->purchaseFor($owner, 'digital-products/missing.zip');

        $this->actingAs($owner)
            ->post(route('purchase.download', $missingPurchase))
            ->assertNotFound();
    }

    public function test_purchase_download_uses_private_file_when_both_private_and_public_exist(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        [$owner] = $this->clientUser();
        $purchase = $this->purchaseFor($owner, 'digital-products/both.zip');
        Storage::disk('local')->put('digital-products/both.zip', 'private');
        Storage::disk('public')->put('digital-products/both.zip', 'public');

        $response = $this->actingAs($owner)
            ->post(route('purchase.download', $purchase))
            ->assertOk();

        $this->assertSame('private', $response->streamedContent());
    }

    public function test_digital_product_migration_command_dry_run_and_copy_are_safe(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $version = DigitalProductVersion::create([
            'digital_product_id' => $this->productFor(User::factory()->create())->id,
            'version_number' => '1.0',
            'file_path' => 'products/files/legacy.zip',
            'is_active' => true,
        ]);
        Storage::disk('public')->put($version->file_path, 'legacy');

        $this->artisan('digital-products:migrate-private --dry-run')
            ->expectsOutputToContain('Would copy')
            ->expectsOutput('migrated: 1')
            ->expectsOutput('already_private: 0')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 0')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($version->file_path);
        Storage::disk('public')->assertExists($version->file_path);

        $this->artisan('digital-products:migrate-private')
            ->expectsOutput('migrated: 1')
            ->expectsOutput('already_private: 0')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 0')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($version->file_path);
        Storage::disk('public')->assertExists($version->file_path);

        $this->artisan('digital-products:migrate-private')
            ->expectsOutput('migrated: 0')
            ->expectsOutput('already_private: 1')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 0')
            ->assertSuccessful();
    }

    public function test_digital_product_migration_delete_public_requires_identical_private_file(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $version = DigitalProductVersion::create([
            'digital_product_id' => $this->productFor(User::factory()->create())->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/identical.zip',
            'is_active' => true,
        ]);

        Storage::disk('local')->put($version->file_path, 'same-content');
        Storage::disk('public')->put($version->file_path, 'same-content');

        $this->artisan('digital-products:migrate-private --delete-public')
            ->expectsOutput('migrated: 0')
            ->expectsOutput('already_private: 1')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 0')
            ->assertSuccessful();

        Storage::disk('local')->assertExists($version->file_path);
        Storage::disk('public')->assertMissing($version->file_path);

        $this->artisan('digital-products:migrate-private --delete-public')
            ->expectsOutput('migrated: 0')
            ->expectsOutput('already_private: 1')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 0')
            ->assertSuccessful();
    }

    public function test_digital_product_migration_conflicting_private_file_fails_without_mutation(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $version = DigitalProductVersion::create([
            'digital_product_id' => $this->productFor(User::factory()->create())->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/conflict.zip',
            'is_active' => true,
        ]);

        Storage::disk('local')->put($version->file_path, 'private-content');
        Storage::disk('public')->put($version->file_path, 'public-content');

        $this->artisan('digital-products:migrate-private --delete-public')
            ->expectsOutputToContain('Conflict')
            ->expectsOutput('migrated: 0')
            ->expectsOutput('already_private: 1')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 1')
            ->assertFailed();

        $this->assertSame('private-content', Storage::disk('local')->get($version->file_path));
        $this->assertSame('public-content', Storage::disk('public')->get($version->file_path));
    }

    public function test_digital_product_migration_copy_delete_public_verifies_integrity_and_dry_run_does_not_mutate(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $product = $this->productFor(User::factory()->create());
        $copy = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'products/files/copy.zip',
            'is_active' => true,
        ]);
        $dryRun = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '2.0',
            'file_path' => 'products/files/dry-run.zip',
            'is_active' => true,
        ]);

        Storage::disk('public')->put($copy->file_path, 'copy-content');
        Storage::disk('public')->put($dryRun->file_path, 'dry-content');

        $this->artisan('digital-products:migrate-private --dry-run --delete-public')
            ->expectsOutputToContain('Would copy')
            ->expectsOutput('migrated: 2')
            ->expectsOutput('already_private: 0')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 0')
            ->assertSuccessful();

        Storage::disk('local')->assertMissing($copy->file_path);
        Storage::disk('public')->assertExists($copy->file_path);

        $this->artisan('digital-products:migrate-private --delete-public')
            ->expectsOutput('migrated: 2')
            ->expectsOutput('already_private: 0')
            ->expectsOutput('missing: 0')
            ->expectsOutput('errors: 0')
            ->assertSuccessful();

        $this->assertSame('copy-content', Storage::disk('local')->get($copy->file_path));
        $this->assertSame('dry-content', Storage::disk('local')->get($dryRun->file_path));
        Storage::disk('public')->assertMissing($copy->file_path);
        Storage::disk('public')->assertMissing($dryRun->file_path);
    }

    public function test_digital_product_migration_command_delete_public_and_path_safety(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $product = $this->productFor(User::factory()->create());
        $legacy = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/delete-public.zip',
            'is_active' => true,
        ]);
        $missing = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '2.0',
            'file_path' => 'digital-products/missing.zip',
            'is_active' => true,
        ]);
        DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '3.0',
            'file_path' => '../outside.zip',
            'is_active' => true,
        ]);
        Storage::disk('public')->put($legacy->file_path, 'legacy');

        $this->artisan('digital-products:migrate-private --delete-public')
            ->expectsOutput('migrated: 1')
            ->expectsOutput('already_private: 0')
            ->expectsOutput('missing: 1')
            ->expectsOutput('errors: 1')
            ->assertFailed();

        Storage::disk('local')->assertExists($legacy->file_path);
        Storage::disk('public')->assertMissing($legacy->file_path);
        Storage::disk('local')->assertMissing($missing->file_path);
    }

    public function test_digital_product_version_and_product_resource_delete_guards_preserve_purchases(): void
    {
        [$owner] = $this->clientUser();
        $product = $this->productFor(User::factory()->create());
        $purchasedVersion = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/purchased.zip',
            'is_active' => true,
        ]);
        $unreferencedVersion = DigitalProductVersion::create([
            'digital_product_id' => $this->productFor(User::factory()->create())->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/unreferenced.zip',
            'is_active' => true,
        ]);

        Purchase::create([
            'user_id' => $owner->id,
            'digital_product_version_id' => $purchasedVersion->id,
            'transaction_id' => 'txn-' . uniqid(),
            'amount' => 25,
            'download_limit' => 5,
            'download_expires_at' => now()->addYear(),
        ]);

        $admin = User::factory()->create(['status' => 'active']);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);

        $this->assertFalse(DigitalProductVersionResource::canDelete($purchasedVersion));
        $this->assertTrue(DigitalProductVersionResource::canDelete($unreferencedVersion));
        $this->assertFalse(DigitalProductResource::canDelete($product));

        $this->assertDatabaseHas('digital_product_versions', ['id' => $purchasedVersion->id]);
        $this->assertDatabaseHas('purchases', ['digital_product_version_id' => $purchasedVersion->id]);
    }

    public function test_digital_product_version_model_rejects_direct_delete_when_purchased(): void
    {
        [$owner] = $this->clientUser();
        $product = $this->productFor(User::factory()->create());
        $purchasedVersion = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/purchased.zip',
            'is_active' => true,
        ]);
        $unreferencedVersion = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '2.0',
            'file_path' => 'digital-products/unreferenced.zip',
            'is_active' => true,
        ]);

        Purchase::create([
            'user_id' => $owner->id,
            'digital_product_version_id' => $purchasedVersion->id,
            'transaction_id' => 'txn-' . uniqid(),
            'amount' => 25,
            'download_limit' => 5,
            'download_expires_at' => now()->addYear(),
        ]);

        try {
            $purchasedVersion->delete();
            $this->fail('Purchased versions must not be directly deleted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cannot delete a purchased digital product version.', $exception->getMessage());
        }

        $this->assertDatabaseHas('digital_product_versions', ['id' => $purchasedVersion->id]);
        $this->assertDatabaseHas('purchases', ['digital_product_version_id' => $purchasedVersion->id]);

        $this->assertTrue($unreferencedVersion->delete());
        $this->assertDatabaseMissing('digital_product_versions', ['id' => $unreferencedVersion->id]);
    }

    public function test_digital_product_model_rejects_direct_delete_when_purchased_and_preserves_media(): void
    {
        Storage::fake('public');

        [$owner] = $this->clientUser();
        $product = $this->productFor(User::factory()->create());
        $product->update([
            'image' => 'products/images/cover.jpg',
            'gallery_images' => ['products/gallery/one.jpg', 'products/gallery/two.jpg'],
        ]);
        $purchasedVersion = DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/purchased.zip',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('products/images/cover.jpg', 'cover');
        Storage::disk('public')->put('products/gallery/one.jpg', 'one');
        Storage::disk('public')->put('products/gallery/two.jpg', 'two');

        Purchase::create([
            'user_id' => $owner->id,
            'digital_product_version_id' => $purchasedVersion->id,
            'transaction_id' => 'txn-' . uniqid(),
            'amount' => 25,
            'download_limit' => 5,
            'download_expires_at' => now()->addYear(),
        ]);

        try {
            $product->delete();
            $this->fail('Products with purchased versions must not be directly deleted.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Cannot delete a digital product with purchased versions.', $exception->getMessage());
        }

        $this->assertDatabaseHas('digital_products', ['id' => $product->id]);
        $this->assertDatabaseHas('digital_product_versions', ['id' => $purchasedVersion->id]);
        $this->assertDatabaseHas('purchases', ['digital_product_version_id' => $purchasedVersion->id]);
        Storage::disk('public')->assertExists('products/images/cover.jpg');
        Storage::disk('public')->assertExists('products/gallery/one.jpg');
        Storage::disk('public')->assertExists('products/gallery/two.jpg');
    }

    public function test_unreferenced_digital_product_delete_preserves_existing_media_cleanup_behavior(): void
    {
        Storage::fake('public');

        $product = $this->productFor(User::factory()->create());
        $product->update([
            'image' => 'products/images/cover.jpg',
            'gallery_images' => ['products/gallery/one.jpg'],
        ]);
        DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/unreferenced.zip',
            'is_active' => true,
        ]);

        Storage::disk('public')->put('products/images/cover.jpg', 'cover');
        Storage::disk('public')->put('products/gallery/one.jpg', 'one');

        $this->assertTrue($product->delete());

        $this->assertDatabaseMissing('digital_products', ['id' => $product->id]);
        Storage::disk('public')->assertMissing('products/images/cover.jpg');
        Storage::disk('public')->assertMissing('products/gallery/one.jpg');
    }

    private function clientUser(): array
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Client');

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return [$user, $client];
    }

    private function productFor(User $user): DigitalProduct
    {
        return DigitalProduct::create([
            'name' => 'Download Product',
            'slug' => 'download-product-' . uniqid(),
            'description' => 'Downloadable product',
            'price' => 25,
            'is_published' => true,
            'user_id' => $user->id,
        ]);
    }

    private function purchaseFor(User $user, string $path): Purchase
    {
        $version = DigitalProductVersion::create([
            'digital_product_id' => $this->productFor(User::factory()->create())->id,
            'version_number' => '1.0',
            'file_path' => $path,
            'is_active' => true,
        ]);

        return Purchase::create([
            'user_id' => $user->id,
            'digital_product_version_id' => $version->id,
            'transaction_id' => 'txn-' . uniqid(),
            'amount' => 25,
            'download_limit' => 5,
            'download_expires_at' => now()->addYear(),
        ]);
    }
}
