<?php

namespace Database\Seeders;

use App\Models\Feature;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\PortfolioProject;
use App\Models\Publication;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PersonalWebsiteContentSeeder extends Seeder
{
    private const DEMO_TESTIMONIAL_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11];
    private const DEMO_PUBLICATION_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];
    private const DEMO_PORTFOLIO_IDS = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18, 19, 20];
    private const DEMO_DIGITAL_PRODUCT_IDS = [11, 12, 13, 14];
    private const DEMO_GUIDE_IDS = [1, 2, 3, 4, 5];
    private const DEMO_SERVICE_IDS = [7, 8, 9, 10];
    private const BER_GE_PORTFOLIO_ID = 21;

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->updatePages();
            $this->updateServices();
            $this->updateFeatures();
            $this->updateMenus();
            $this->updateSettings();
            $this->hideDemoContent();
        });

        Cache::forget('home.page');
        Cache::forget('site.settings');
        Cache::forget('home.section.services');
        Cache::forget('home.section.projects');
        Cache::forget('home.section.publications');
        Cache::forget('home.section.testimonials');
        Cache::forget('home.section.features');
    }

    private function updatePages(): void
    {
        $aboutContent = <<<'HTML'
<p>I'm Besarion Archvadze, a full-stack web developer based in Georgia with more than 15 years of hands-on experience in web development.</p>
<p>I work directly with businesses on websites and web applications that need solid technical implementation - from the database and backend to the public interface, integrations and deployment.</p>
<p>My work is mainly focused on PHP/Laravel, JavaScript/TypeScript, SQL databases, APIs and Linux/Docker-based environments. I also work with existing systems that need maintenance, modernization or new functionality.</p>
<p>For clients, that means fewer hand-offs between separate specialists and a clear technical point of contact throughout the project.</p>
HTML;

        $this->upsertPage('home', [
            'title' => 'Home',
            'status' => 'published',
            'hero_title' => 'Websites and web systems built for real business.',
            'hero_subtitle' => 'I design, build and improve websites and web applications for companies in Georgia and international teams - from business websites and online sales to custom workflows, integrations and ongoing technical support.',
            'hero_button_text' => 'Discuss Your Project',
            'hero_button_url' => '/contact',
            'portfolio_title' => 'Selected Work',
            'portfolio_subtitle' => 'A selection of websites, platforms and technical projects I have worked on.',
            'portfolio_items_count' => 3,
            'services_title' => 'What I Can Help You Build',
            'services_subtitle' => 'Some businesses need a better website. Others need a client portal, payment integration, internal tool or an existing system fixed. I work across the full web stack, so the solution does not have to stop at the front end.',
            'services_items_count' => 6,
            'features_title' => 'Direct technical collaboration',
            'features_subtitle' => 'You work directly with the developer doing the work, with practical technical decisions explained in business terms.',
            'features_items_count' => 4,
            'testimonials_title' => 'Client Feedback',
            'testimonials_items_count' => 0,
            'blog_title' => 'Notes',
            'blog_subtitle' => 'Practical notes on web development and business systems.',
            'blog_items_count' => 0,
            'show_portfolio' => true,
            'show_services' => true,
            'show_features' => true,
            'show_testimonials' => false,
            'show_blog' => false,
            'section_order' => [
                'services' => 1,
                'portfolio' => 2,
                'features' => 3,
                'testimonials' => 4,
                'blog' => 5,
            ],
            'seo_title' => 'Besarion Archvadze | Full-Stack Web Developer in Georgia',
            'seo_description' => 'Full-stack web development for businesses in Georgia and international clients. Business websites, custom web applications, Laravel development, integrations and technical improvements.',
        ]);

        $this->upsertPage('services', [
            'title' => 'Services',
            'status' => 'published',
            'page_title' => 'Web Development Services',
            'page_subtitle' => 'Business websites, custom web applications, e-commerce, Laravel development, API integrations and technical improvements.',
            'seo_title' => 'Web Development Services | Besarion Archvadze',
            'seo_description' => 'Business websites, custom web applications, e-commerce, Laravel development, API integrations and technical improvement services.',
        ]);

        $this->upsertPage('portfolio', [
            'title' => 'Portfolio',
            'status' => 'published',
            'page_title' => 'Selected Work',
            'page_subtitle' => 'A selection of websites, applications and technical projects.',
            'items_count' => 9,
            'seo_title' => 'Web Development Portfolio | Besarion Archvadze',
            'seo_description' => 'Selected web development projects, applications and technical work by Besarion Archvadze.',
        ]);

        $this->upsertPage('about', [
            'title' => 'About',
            'status' => 'published',
            'page_title' => 'About Me',
            'page_subtitle' => 'Senior full-stack developer based in Georgia.',
            'hero_title' => null,
            'hero_subtitle' => null,
            'hero_button_text' => 'Discuss Your Project',
            'hero_button_url' => '/contact',
            'content' => $aboutContent,
            'seo_title' => 'About Besarion Archvadze | Full-Stack Developer',
            'seo_description' => 'About Besarion Archvadze, a Georgia-based full-stack web developer with 15+ years of hands-on web development experience.',
        ]);

        $this->upsertPage('contact', [
            'title' => 'Contact',
            'status' => 'published',
            'page_title' => 'Discuss Your Web Project',
            'page_subtitle' => 'Have a website or web project in mind? Tell me what you need, what already exists and where the main problem is. I can help determine the practical next step.',
            'hero_title' => null,
            'hero_subtitle' => null,
            'seo_title' => 'Discuss Your Web Project | Besarion Archvadze',
            'seo_description' => 'Contact Besarion Archvadze to discuss a business website, web application, Laravel project, integration or technical improvement.',
        ]);
    }

    private function updateServices(): void
    {
        $services = [
            [
                'id' => 1,
                'name' => 'Business Websites',
                'description' => 'Professional websites for companies that need to explain what they do clearly, build trust and turn visitors into enquiries. This can include corporate sites, service websites, landing pages, multilingual websites and custom CMS implementations with responsive development, SEO foundations and practical content management.',
                'icon' => 'briefcase',
            ],
            [
                'id' => 2,
                'name' => 'Custom Web Applications',
                'description' => 'Web-based systems built around business workflows rather than a pre-made template. Typical work includes client portals, dashboards, internal tools, booking or workflow systems, document systems and custom administration interfaces.',
                'icon' => 'layer-group',
            ],
            [
                'id' => 3,
                'name' => 'E-commerce & Online Payments',
                'description' => 'Online sales and payment workflows built with clear product management, checkout and reliable payment integration. This can include product catalogs, digital products, checkout flows, PayPal/payment integrations and customer areas.',
                'icon' => 'cart-shopping',
            ],
            [
                'id' => 4,
                'name' => 'API Integrations & Automation',
                'description' => 'Connect websites and business systems so information does not have to be moved manually between tools. I work with third-party APIs, email systems, payment services, business tools, workflow automation and practical AI-assisted functionality where it helps the process.',
                'icon' => 'plug',
            ],
            [
                'id' => 5,
                'name' => 'Laravel & PHP Development',
                'description' => 'Backend development, maintenance and modernization for existing PHP and Laravel applications. Work can include new features, API development, database changes, bug fixing, architecture improvements and gradual modernization without forcing a full rebuild.',
                'icon' => 'code',
            ],
            [
                'id' => 6,
                'name' => 'Performance & Technical Improvements',
                'description' => 'Technical work for websites and applications that are slow, difficult to maintain or no longer working reliably. Areas include performance, technical SEO, security hardening, deployment, server/application configuration, code cleanup and bug fixing.',
                'icon' => 'gauge-high',
            ],
        ];

        foreach ($services as $serviceData) {
            $service = Service::find($serviceData['id']) ?? new Service();
            if (! $service->exists) {
                $service->id = $serviceData['id'];
            }

            unset($serviceData['id']);

            $service->fill($serviceData + [
                'status' => true,
                'is_active' => true,
                'button_text' => 'Discuss Your Project',
            ]);
            $service->base_price = 0;
            $service->save();
        }

        Service::whereIn('id', self::DEMO_SERVICE_IDS)
            ->update([
                'status' => false,
                'is_active' => false,
            ]);
    }

    private function updateFeatures(): void
    {
        $features = [
            [
                'id' => 1,
                'name' => 'Direct collaboration',
                'description' => 'You work directly with the developer doing the work.',
                'icon' => 'comments',
            ],
            [
                'id' => 2,
                'name' => 'Full-stack delivery',
                'description' => 'Frontend, backend, database and integrations can be handled in one place.',
                'icon' => 'code-branch',
            ],
            [
                'id' => 3,
                'name' => 'Improve what exists',
                'description' => 'Existing systems can often be improved instead of rebuilt unnecessarily.',
                'icon' => 'screwdriver-wrench',
            ],
            [
                'id' => 4,
                'name' => 'Production-minded work',
                'description' => 'Projects are built with maintainability and real business use in mind.',
                'icon' => 'server',
            ],
        ];

        foreach ($features as $featureData) {
            $feature = Feature::find($featureData['id']) ?? new Feature();
            if (! $feature->exists) {
                $feature->id = $featureData['id'];
            }

            unset($featureData['id']);

            $feature->fill($featureData);
            if (! $feature->exists) {
                $feature->price = 0;
            }
            $feature->save();
        }
    }

    private function updateMenus(): void
    {
        $headerItems = [
            ['label' => 'Home', 'url' => '/', 'position' => 1],
            ['label' => 'Services', 'url' => '/services', 'position' => 2],
            ['label' => 'Portfolio', 'url' => '/portfolio', 'position' => 3],
            ['label' => 'About', 'url' => '/about', 'position' => 4],
            ['label' => 'Contact', 'url' => '/contact', 'position' => 5],
        ];

        $footerItems = [
            ['label' => 'Home', 'url' => '/', 'position' => 1],
            ['label' => 'Services', 'url' => '/services', 'position' => 2],
            ['label' => 'Portfolio', 'url' => '/portfolio', 'position' => 3],
            ['label' => 'About', 'url' => '/about', 'position' => 4],
            ['label' => 'Contact', 'url' => '/contact', 'position' => 5],
        ];

        foreach ($headerItems as $item) {
            $this->upsertMenuItem('header', $item['url'], $item);
        }

        foreach ($footerItems as $item) {
            $this->upsertMenuItem('footer', $item['url'], $item);
        }

        MenuItem::where('location', 'header')
            ->whereIn('url', ['/shop', '/blog', '/guides'])
            ->update(['is_active' => false]);

        MenuItem::where('location', 'footer')
            ->whereIn('url', ['/page/test', '/pop', '/faq', '/testimonials'])
            ->update(['is_active' => false]);

        $this->upsertMenuItem('bottom', '/privacy-policy', [
            'label' => 'Privacy Policy',
            'url' => '/privacy-policy',
            'position' => 1,
        ]);

        $this->upsertMenuItem('bottom', '/terms', [
            'label' => 'Terms of Service',
            'url' => '/terms',
            'position' => 2,
        ]);
    }

    private function updateSettings(): void
    {
        $settings = [
            'site_name' => 'Besarion Archvadze',
            'footer_tagline' => 'Web development and technical solutions for businesses in Georgia and international clients.',
            'seo_default_title' => 'Besarion Archvadze | Full-Stack Web Developer in Georgia',
            'seo_default_description' => 'Full-stack web development for business websites, custom web applications, Laravel development, integrations and technical improvements.',
            'copyright_text' => 'Copyright {year} Besarion Archvadze. All rights reserved.',
            'module_blog_label' => 'Blog',
            'module_guides_label' => 'Guides',
            'module_portfolio_label' => 'Portfolio',
            'module_services_label' => 'Services',
            'module_shop_label' => 'Shop',
            'module_testimonials_label' => 'Testimonials',
        ];

        foreach ($settings as $key => $value) {
            SiteSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    private function hideDemoContent(): void
    {
        Testimonial::whereIn('id', self::DEMO_TESTIMONIAL_IDS)->update([
            'is_featured' => false,
            'is_published' => false,
        ]);

        Publication::whereIn('id', self::DEMO_PUBLICATION_IDS)->update([
            'status' => 'draft',
            'is_published' => false,
        ]);

        PortfolioProject::whereIn('id', self::DEMO_PORTFOLIO_IDS)->update([
            'is_featured' => false,
            'is_published' => false,
        ]);

        PortfolioProject::where('id', self::BER_GE_PORTFOLIO_ID)->update([
            'is_featured' => true,
            'is_published' => true,
        ]);

        if (Schema::hasTable('digital_products') && Schema::hasColumn('digital_products', 'is_published')) {
            DB::table('digital_products')
                ->whereIn('id', self::DEMO_DIGITAL_PRODUCT_IDS)
                ->update(['is_published' => false]);
        }

        if (Schema::hasTable('guides') && Schema::hasColumn('guides', 'published_at')) {
            DB::table('guides')
                ->whereIn('id', self::DEMO_GUIDE_IDS)
                ->update(['published_at' => null]);
        }

        Page::whereIn('slug', ['test', 'pop'])->update(['status' => 'draft']);
    }

    private function upsertPage(string $slug, array $attributes): void
    {
        Page::updateOrCreate(['slug' => $slug], $attributes + ['slug' => $slug]);
    }

    private function upsertMenuItem(string $location, string $url, array $attributes): void
    {
        MenuItem::updateOrCreate(
            [
                'location' => $location,
                'url' => $url,
            ],
            $attributes + [
                'location' => $location,
                'url' => $url,
                'is_active' => true,
                'open_in_new_tab' => false,
            ]
        );
    }
}
