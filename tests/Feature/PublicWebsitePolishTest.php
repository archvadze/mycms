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
            ->assertSee('Services and Features')
            ->assertSee('value="'.$service->id.'"', false)
            ->assertSee('checked', false);
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
