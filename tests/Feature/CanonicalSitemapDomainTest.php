<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanonicalSitemapDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_uses_preferred_canonical_domain(): void
    {
        config()->set('app.url', 'https://archvadze.com');

        $this->get('/')
            ->assertOk()
            ->assertSee(
                '<link rel="canonical" href="https://archvadze.com">',
                false
            )
            ->assertSee(
                '<meta property="og:url" content="https://archvadze.com">',
                false
            );
    }

    public function test_sitemap_uses_preferred_domain(): void
    {
        config()->set('app.url', 'https://archvadze.com');

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertSee(
                '<loc>https://archvadze.com/</loc>',
                false
            )
            ->assertSee(
                '<loc>https://archvadze.com/services</loc>',
                false
            )
            ->assertDontSee('https://www.archvadze.com');
    }
}
