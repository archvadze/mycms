<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\DigitalProduct;
use App\Models\DigitalProductVersion;
use App\Models\Guide;
use App\Models\PortfolioProject;
use App\Models\Publication;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PublicWebsitePolishTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        Role::create(['name' => 'Client', 'guard_name' => 'web']);
    }

    public function test_homepage_uses_primary_project_cta_and_only_published_featured_proof(): void
    {
        $publishedProject = PortfolioProject::factory()->published()->create([
            'title' => 'Published Featured Project',
            'is_featured' => true,
        ]);
        PortfolioProject::factory()->unpublished()->create([
            'title' => 'Hidden Featured Project',
            'is_featured' => true,
        ]);

        Testimonial::factory()->published()->create([
            'client_name' => 'Published Client',
            'is_featured' => true,
        ]);
        Testimonial::factory()->unpublished()->create([
            'client_name' => 'Hidden Client',
            'is_featured' => true,
        ]);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Start a Project')
            ->assertSee('View Services')
            ->assertSee($publishedProject->title)
            ->assertSee('Published Client')
            ->assertDontSee('Hidden Featured Project')
            ->assertDontSee('Hidden Client');
    }

    public function test_public_empty_states_are_useful(): void
    {
        $this->get(route('portfolio'))
            ->assertOk()
            ->assertSee('Portfolio examples are being updated');

        $this->get(route('guides'))
            ->assertOk()
            ->assertSee('No guides published yet');

        $this->get(route('blog'))
            ->assertOk()
            ->assertSee('No publications available');

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('No products found');
    }

    public function test_services_page_uses_start_project_cta_and_preselects_requested_service(): void
    {
        $service = Service::factory()->create([
            'name' => 'Conversion Design',
            'status' => true,
        ]);

        $this->get(route('services'))
            ->assertOk()
            ->assertSee('Start a Project')
            ->assertSee(route('order.create', absolute: false).'?service='.$service->id, false);

        $this->actingAs($this->clientUser())
            ->get(route('order.create', ['service' => $service->id]))
            ->assertOk()
            ->assertSee('Services Required')
            ->assertSee('value="'.$service->id.'"', false)
            ->assertSee('checked', false);
    }

    public function test_homepage_service_images_are_lazy_loaded_with_alt_text_and_stable_dimensions(): void
    {
        $service = Service::factory()->create([
            'name' => 'Business Websites',
            'description' => 'Clear business websites.',
            'image' => 'services/business-websites.webp',
            'status' => true,
            'is_active' => true,
        ]);

        $response = $this->get(route('home'));

        $response
            ->assertOk()
            ->assertSee('src="'.asset('storage/'.$service->image).'"', false)
            ->assertSee('alt="Business Websites"', false)
            ->assertSee('width="1200"', false)
            ->assertSee('height="675"', false)
            ->assertSee('loading="lazy"', false)
            ->assertSee('decoding="async"', false)
            ->assertDontSee('src=""', false)
            ->assertSee(route('services', absolute: false), false);
    }

    public function test_services_page_prioritizes_only_first_service_image_and_lazily_loads_the_rest(): void
    {
        $first = Service::factory()->create([
            'name' => 'Business Websites',
            'image' => 'services/business-websites.webp',
            'status' => true,
            'is_active' => true,
            'created_at' => now()->subMinutes(3),
        ]);
        $second = Service::factory()->create([
            'name' => 'Custom Web Applications',
            'image' => 'services/custom-web-applications.webp',
            'status' => true,
            'is_active' => true,
            'created_at' => now()->subMinutes(2),
        ]);
        $third = Service::factory()->create([
            'name' => 'Laravel Development',
            'image' => 'services/laravel-development.webp',
            'status' => true,
            'is_active' => true,
            'created_at' => now()->subMinute(),
        ]);

        $html = $this->get(route('services'))
            ->assertOk()
            ->assertSee('src="'.asset('storage/'.$first->image).'"', false)
            ->assertSee('alt="Business Websites"', false)
            ->assertSee('src="'.asset('storage/'.$second->image).'"', false)
            ->assertSee('alt="Custom Web Applications"', false)
            ->assertSee('src="'.asset('storage/'.$third->image).'"', false)
            ->assertSee('alt="Laravel Development"', false)
            ->assertSee(route('order.create', absolute: false).'?service='.$first->id, false)
            ->assertDontSee('src=""', false)
            ->getContent();

        $this->assertSame(1, substr_count($html, 'fetchpriority="high"'));
        $this->assertSame(1, substr_count($html, 'loading="eager"'));
        $this->assertSame(2, substr_count($html, 'loading="lazy"'));
        $this->assertSame(2, substr_count($html, 'decoding="async"'));
        $this->assertStringContainsString('width="1200"', $html);
        $this->assertStringContainsString('height="675"', $html);
    }

    public function test_shop_detail_does_not_expose_private_file_path(): void
    {
        $product = DigitalProduct::create([
            'name' => 'Launch Kit',
            'slug' => 'launch-kit',
            'short_description' => 'A practical launch template.',
            'description' => 'Product details',
            'category' => 'templates',
            'price' => 49,
            'is_published' => true,
            'user_id' => User::factory()->create()->id,
        ]);

        DigitalProductVersion::create([
            'digital_product_id' => $product->id,
            'version_number' => '1.0',
            'file_path' => 'digital-products/private/launch-kit.zip',
            'is_active' => true,
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('What you receive')
            ->assertSee('Purchase')
            ->assertDontSee('digital-products/private/launch-kit.zip');
    }

    public function test_order_form_validation_remains_server_side(): void
    {
        $this->actingAs($this->clientUser())
            ->from(route('order.create'))
            ->post(route('order.store'), [])
            ->assertRedirect(route('order.create'))
            ->assertSessionHasErrors([
                'client_name',
                'email',
                'website_type',
                'timeline',
                'budget_range',
                'services',
                'project_description',
            ]);
    }

    private function clientUser(): User
    {
        $user = User::factory()->create([
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
        $user->assignRole('Client');

        Client::factory()->create([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ]);

        return $user;
    }
}
