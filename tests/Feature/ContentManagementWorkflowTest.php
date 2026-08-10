<?php

namespace Tests\Feature;

use App\Filament\Pages\ManageSettings;
use App\Filament\Resources\MenuItemResource;
use App\Filament\Resources\PublicationResource;
use App\Filament\Resources\ServiceResource;
use App\Models\DigitalProduct;
use App\Models\Guide;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Publication;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ContentManagementWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);
        Role::create(['name' => 'Editor', 'guard_name' => 'web']);
        Role::create(['name' => 'Support', 'guard_name' => 'web']);
    }

    public function test_publication_publish_action_updates_existing_fields_and_clears_home_cache(): void
    {
        Http::fake();
        Cache::put('home.publications', 'stale', 3600);

        $publication = Publication::factory()->draft()->create();

        PublicationResource::setPublished($publication, true);

        $publication->refresh();

        $this->assertTrue($publication->is_published);
        $this->assertSame('published', $publication->status);
        $this->assertNotNull($publication->published_at);
        $this->assertFalse(Cache::has('home.publications'));
    }

    public function test_service_active_action_keeps_status_fields_aligned_and_clears_home_cache(): void
    {
        Cache::put('home.services', 'stale', 3600);

        $service = Service::factory()->create([
            'status' => true,
            'is_active' => true,
        ]);

        ServiceResource::setActive($service, false);

        $service->refresh();

        $this->assertFalse($service->status);
        $this->assertFalse($service->is_active);
        $this->assertFalse(Cache::has('home.services'));
    }

    public function test_menu_item_active_action_clears_menu_cache(): void
    {
        Cache::put('menu.items', 'stale', 3600);

        $menuItem = MenuItem::create([
            'label' => 'Services',
            'url' => '/services',
            'location' => 'header',
            'position' => 1,
            'is_active' => true,
        ]);

        MenuItemResource::setActive($menuItem, false);

        $this->assertFalse($menuItem->refresh()->is_active);
        $this->assertFalse(Cache::has('menu.items'));
    }

    public function test_site_setting_updates_clear_site_settings_cache(): void
    {
        Cache::put('site.settings', ['site_name' => 'Old'], 3600);

        SiteSetting::updateOrCreate(
            ['key' => 'site_name'],
            ['value' => 'New']
        );

        $this->assertFalse(Cache::has('site.settings'));
    }

    public function test_settings_page_access_stays_super_admin_only(): void
    {
        $support = User::factory()->create(['status' => 'active']);
        $support->assignRole('Support');

        $this->actingAs($support);

        $this->assertFalse(ManageSettings::canAccess());

        $superAdmin = User::factory()->create(['status' => 'active']);
        $superAdmin->assignRole('Super Admin');

        $this->actingAs($superAdmin);

        $this->assertTrue(ManageSettings::canAccess());
    }

    public function test_public_layout_uses_old_seo_fallback_when_setting_is_missing_or_blank(): void
    {
        $this->withoutVite();

        $missing = $this->renderPublicLayout([]);
        $this->assertStringContainsString(
            'content="Professional web design and development services"',
            $missing
        );

        $blank = $this->renderPublicLayout(['seo_default_description' => '']);
        $this->assertStringContainsString(
            'content="Professional web design and development services"',
            $blank
        );
    }

    public function test_public_layout_uses_configured_seo_description_when_present(): void
    {
        $this->withoutVite();

        $html = $this->renderPublicLayout([
            'seo_default_description' => 'Configured SEO description.',
        ]);

        $this->assertStringContainsString(
            'content="Configured SEO description."',
            $html
        );
    }

    public function test_public_layout_uses_blank_safe_copyright_fallback_or_configured_value(): void
    {
        $this->withoutVite();

        $missing = $this->renderPublicLayout(['site_name' => 'archvadze']);
        $this->assertStringContainsString(
            '© ' . date('Y') . ' archvadze. All rights reserved.',
            $missing
        );

        $blank = $this->renderPublicLayout([
            'site_name' => 'archvadze',
            'copyright_text' => '',
        ]);
        $this->assertStringContainsString(
            '© ' . date('Y') . ' archvadze. All rights reserved.',
            $blank
        );

        $configured = $this->renderPublicLayout([
            'copyright_text' => 'Copyright {year} Example CMS',
        ]);
        $this->assertStringContainsString(
            'Copyright ' . date('Y') . ' Example CMS',
            $configured
        );
    }

    public function test_public_guides_only_expose_published_records(): void
    {
        Page::create([
            'title' => 'Guides',
            'slug' => 'guides',
            'content' => '',
            'items_count' => 12,
        ]);

        $published = Guide::factory()->create([
            'title' => 'Published Guide',
            'slug' => 'published-guide',
            'published_at' => now(),
        ]);

        $unpublished = Guide::factory()->create([
            'title' => 'Unpublished Guide',
            'slug' => 'unpublished-guide',
            'published_at' => null,
        ]);

        $this->get(route('guides'))
            ->assertOk()
            ->assertSeeText($published->title)
            ->assertDontSeeText($unpublished->title);

        $this->get(route('guides.show', $unpublished->slug))->assertNotFound();
        $this->get(route('guides.show', $published->slug))
            ->assertOk()
            ->assertSeeText($published->title);
    }

    public function test_public_portfolio_listing_only_exposes_published_records(): void
    {
        Page::create([
            'title' => 'Portfolio',
            'slug' => 'portfolio',
            'content' => '',
            'items_count' => 12,
        ]);

        $published = PortfolioProject::factory()->published()->create([
            'title' => 'Published Portfolio Project',
        ]);

        $unpublished = PortfolioProject::factory()->unpublished()->create([
            'title' => 'Unpublished Portfolio Project',
        ]);

        $this->get(route('portfolio'))
            ->assertOk()
            ->assertSeeText($published->title)
            ->assertDontSeeText($unpublished->title);
    }

    public function test_shop_checkout_success_does_not_resolve_unpublished_products(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $product = DigitalProduct::create([
            'name' => 'Unpublished Product',
            'slug' => 'unpublished-product',
            'description' => 'Hidden',
            'price' => 100,
            'is_published' => false,
            'user_id' => $user->id,
        ]);

        $this->actingAs($user)
            ->withSession(['shop_version_id' => 123])
            ->get(route('shop.checkout.success', [
                'slug' => $product->slug,
                'token' => 'paypal-token',
            ]))
            ->assertNotFound();
    }

    private function renderPublicLayout(array $siteSettings): string
    {
        SiteSetting::query()->delete();

        foreach ($siteSettings as $key => $value) {
            SiteSetting::create([
                'key' => $key,
                'value' => $value,
            ]);
        }

        Cache::forget('site.settings');
        Cache::forget('menu.items');

        return view('layouts.main', [
            'headerMenuItems' => new Collection(),
            'footerMenuItems' => new Collection(),
            'bottomMenuItems' => new Collection(),
        ])->render();
    }
}
