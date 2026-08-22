<?php

namespace Tests\Feature;

use App\Models\DigitalProduct;
use App\Models\Faq;
use App\Models\FaqCategory;
use App\Models\Guide;
use App\Models\GuideCategory;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Publication;
use App\Models\SiteSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class AuditFixImplementationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.url' => 'https://archvadze.com']);
        Cache::flush();
    }

    public function test_public_pages_render_page_specific_metadata_and_primary_canonical(): void
    {
        $this->page([
            'slug' => 'home',
            'title' => 'Home',
            'seo_title' => 'Home SEO Title',
            'seo_description' => 'Home SEO description.',
        ]);

        $this->page([
            'slug' => 'services',
            'title' => 'Services',
            'seo_title' => 'Services SEO Title',
            'seo_description' => 'Services SEO description.',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('<title>Home SEO Title</title>', false)
            ->assertSee('<meta name="description" content="Home SEO description.">', false)
            ->assertSee('<link rel="canonical" href="https://archvadze.com">', false)
            ->assertSee('<meta property="og:url" content="https://archvadze.com">', false);

        $this->withServerVariables(['HTTP_HOST' => 'www.archvadze.com'])
            ->get('/services')
            ->assertOk()
            ->assertSee('<title>Services SEO Title</title>', false)
            ->assertSee('<meta name="description" content="Services SEO description.">', false)
            ->assertSee('<link rel="canonical" href="https://archvadze.com/services">', false)
            ->assertSee('<meta property="og:url" content="https://archvadze.com/services">', false);
    }

    public function test_dynamic_detail_pages_render_record_specific_metadata(): void
    {
        $author = User::factory()->create();
        $publication = Publication::factory()->published()->create([
            'author_id' => $author->id,
            'title' => 'Article Specific Title',
            'slug' => 'article-specific-title',
            'excerpt' => 'Article specific excerpt.',
            'cover_image' => 'publications/article.webp',
        ]);

        $category = GuideCategory::factory()->create();
        $guide = Guide::factory()->create([
            'guide_category_id' => $category->id,
            'title' => 'Guide Specific Title',
            'slug' => 'guide-specific-title',
            'content' => '<p>Guide content description for metadata.</p>',
            'published_at' => now(),
        ]);

        $product = $this->product([
            'name' => 'Product Specific Title',
            'slug' => 'product-specific-title',
            'short_description' => 'Product specific short description.',
            'image' => 'products/covers/product.png',
        ]);

        $this->get(route('blog.show', $publication->slug))
            ->assertOk()
            ->assertSee('<title>Article Specific Title - Archvadze Blog</title>', false)
            ->assertSee('<meta name="description" content="Article specific excerpt.">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee(asset('storage/publications/article.webp'), false);

        $this->get(route('guides.show', $guide->slug))
            ->assertOk()
            ->assertSee('<title>Guide Specific Title - Archvadze Guides</title>', false)
            ->assertSee('Guide content description for metadata.', false)
            ->assertSee('<meta property="og:type" content="article">', false);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertSee('<title>Product Specific Title - Archvadze</title>', false)
            ->assertSee('<meta name="description" content="Product specific short description.">', false)
            ->assertSee('<meta property="og:type" content="product">', false)
            ->assertSee(asset('storage/products/covers/product.png'), false);
    }

    public function test_empty_og_image_is_not_rendered(): void
    {
        $product = $this->product([
            'name' => 'No Image Product',
            'slug' => 'no-image-product',
            'image' => null,
        ]);

        $this->get(route('shop.show', $product->slug))
            ->assertOk()
            ->assertDontSee('property="og:image"', false)
            ->assertDontSee('name="twitter:image"', false);
    }

    public function test_static_robots_txt_contains_literal_production_sitemap_url(): void
    {
        $path = public_path('robots.txt');

        $this->assertFileExists($path);

        $contents = file_get_contents($path);

        $this->assertStringContainsString('Sitemap: https://archvadze.com/sitemap.xml', $contents);
        $this->assertStringNotContainsString('{{', $contents);
        $this->assertStringNotContainsString('}}', $contents);
    }

    public function test_sitemap_contains_valid_public_urls_and_no_portfolio_detail_routes(): void
    {
        $publication = Publication::factory()->published()->create(['slug' => 'valid-article']);
        $category = GuideCategory::factory()->create();
        $guide = Guide::factory()->create([
            'guide_category_id' => $category->id,
            'slug' => 'valid-guide',
            'published_at' => now(),
        ]);
        $product = $this->product(['slug' => 'valid-product']);

        PortfolioProject::factory()->published()->create([
            'slug' => 'House-projects',
            'title' => 'House Project',
        ]);

        $response = $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee('https://archvadze.com/blog/'.$publication->slug, false)
            ->assertSee('https://archvadze.com/guides/'.$guide->slug, false)
            ->assertSee('https://archvadze.com/shop/'.$product->slug, false)
            ->assertDontSee('/portfolio/House-projects', false);

        $this->assertStringNotContainsString('/portfolio/House-projects', $response->getContent());
    }

    public function test_product_image_rendering_avoids_empty_src_and_renders_existing_images(): void
    {
        $noImageProduct = $this->product([
            'name' => 'No Image Product',
            'slug' => 'no-image-product',
            'image' => null,
        ]);

        $withImageProduct = $this->product([
            'name' => 'Image Product',
            'slug' => 'image-product',
            'image' => 'products/covers/image-product.png',
        ]);

        $this->get(route('shop.show', $noImageProduct->slug))
            ->assertOk()
            ->assertDontSee('src=""', false)
            ->assertDontSee('id="main-image"', false);

        $this->get(route('shop.show', $withImageProduct->slug))
            ->assertOk()
            ->assertSee('src="'.asset('storage/products/covers/image-product.png').'"', false)
            ->assertSee('alt="Image Product"', false);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('src="'.asset('storage/products/covers/image-product.png').'"', false)
            ->assertDontSee('src=""', false);
    }

    public function test_footer_social_icon_links_have_accessible_names(): void
    {
        foreach ([
            'social_facebook' => 'https://facebook.example',
            'social_twitter' => 'https://twitter.example',
            'social_instagram' => 'https://instagram.example',
            'social_linkedin' => 'https://linkedin.example',
        ] as $key => $value) {
            SiteSetting::create(['key' => $key, 'value' => $value]);
        }

        Cache::flush();

        $this->get('/')
            ->assertOk()
            ->assertSee('aria-label="Facebook"', false)
            ->assertSee('aria-label="X / Twitter"', false)
            ->assertSee('aria-label="Instagram"', false)
            ->assertSee('aria-label="LinkedIn"', false);
    }

    public function test_portfolio_cover_image_uses_project_title_alt_text(): void
    {
        $project = PortfolioProject::factory()->published()->create([
            'title' => 'Contextual Portfolio Title',
            'cover_image' => 'portfolio/contextual.png',
        ]);

        $this->get(route('portfolio'))
            ->assertOk()
            ->assertSee('src="'.asset('storage/'.$project->cover_image).'"', false)
            ->assertSee('alt="Contextual Portfolio Title"', false);
    }

    public function test_faq_page_does_not_use_missing_x_collapse_behavior(): void
    {
        $category = FaqCategory::factory()->create(['name' => 'Technical']);
        Faq::factory()->create([
            'category_id' => $category->id,
            'question' => 'Can the FAQ open?',
            'answer' => 'Yes.',
        ]);

        $this->get(route('faq'))
            ->assertOk()
            ->assertSee('Can the FAQ open?')
            ->assertDontSee('x-collapse', false);
    }

    private function page(array $attributes): Page
    {
        return Page::create(array_merge([
            'title' => 'Page',
            'slug' => 'page',
            'content' => '',
            'status' => 'published',
        ], $attributes));
    }

    private function product(array $attributes = []): DigitalProduct
    {
        return DigitalProduct::create(array_merge([
            'name' => 'Digital Product',
            'slug' => 'digital-product',
            'short_description' => 'Short product description.',
            'description' => 'Long product description.',
            'category' => 'templates',
            'price' => 19,
            'is_published' => true,
            'user_id' => User::factory()->create()->id,
        ], $attributes));
    }
}
