<?php

namespace App\Console\Commands;

use App\Support\HomepageCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ApplyProfessionalContentRefresh extends Command
{
    protected $signature = 'content:apply-professional-refresh {--force : Run in production without interactive confirmation}';

    protected $description = 'Apply the approved professional public CMS content refresh.';

    public function handle(): int
    {
        if (app()->environment('production') && ! $this->option('force')) {
            if (! $this->confirm('Apply the approved professional public CMS content refresh to production?')) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        foreach ($this->products() as $product) {
            if (! Storage::disk('local')->exists($product['file_path'])) {
                $this->error("Missing required private product file: {$product['file_path']}");

                return self::FAILURE;
            }
        }

        DB::transaction(function (): void {
            $this->updatePages();
            $this->updateServices();
            $this->updateFeatures();
            $this->updateFaq();
            $this->updateNavigationLabels();
            $this->updatePortfolio();
            $this->updateTestimonials();
            $this->updatePublications();
            $this->updateGuides();
            $this->updateProducts();
            $this->updateSettings();
        });

        Cache::forget('home.page');
        Cache::forget('site.settings');
        Cache::forget('menu.items');
        HomepageCache::forgetAll();

        $this->info('Professional public CMS content refresh applied.');

        return self::SUCCESS;
    }

    private function updatePages(): void
    {
        foreach ($this->pages() as $id => $fields) {
            if (array_key_exists('content', $fields)) {
                $fields['content'] = $this->blockHtml($fields['content']);
            }

            DB::table('pages')->where('id', $id)->update($fields);
        }
    }

    private function updateServices(): void
    {
        foreach ($this->services() as $id => $fields) {
            DB::table('services')->where('id', $id)->update($fields);
        }
    }

    private function updateFeatures(): void
    {
        foreach ($this->features() as $id => $fields) {
            DB::table('features')->where('id', $id)->update($fields);
        }
    }

    private function updateFaq(): void
    {
        foreach ($this->faq() as $id => $fields) {
            DB::table('faq')->where('id', $id)->update($fields);
        }
    }

    private function updateNavigationLabels(): void
    {
        DB::table('menu_items')->where('id', 13)->update(['label' => 'How I Work']);
        DB::table('menu_items')->where('id', 15)->update(['label' => 'Working Principles']);
    }

    private function updatePortfolio(): void
    {
        DB::table('portfolio_projects')->whereBetween('id', [1, 20])->update(['is_published' => 0]);
        DB::table('portfolio_projects')->where('id', 21)->update([
            'description' => 'Business website project for ber.ge. A fuller case study with project details and screenshots will be added to the portfolio.',
        ]);
    }

    private function updateTestimonials(): void
    {
        DB::table('testimonials')->whereBetween('id', [1, 11])->update(['is_published' => 0]);
    }

    private function updatePublications(): void
    {
        foreach ($this->publications() as $id => $fields) {
            $fields['content'] = $this->blockHtml($fields['content']);
            DB::table('publications')->where('id', $id)->update($fields);
        }

        DB::table('publications')
            ->whereBetween('id', [7, 15])
            ->update([
                'status' => 'draft',
                'is_published' => 0,
            ]);
    }

    private function updateGuides(): void
    {
        DB::table('guide_categories')->where('id', 3)->update(['name' => 'Web Development']);

        foreach ($this->guides() as $id => $fields) {
            $fields['content'] = $this->blockHtml($fields['content']);
            DB::table('guides')->where('id', $id)->update($fields);
        }
    }

    private function updateProducts(): void
    {
        foreach ([11, 13, 15] as $id) {
            DB::table('digital_products')->where('id', $id)->update(['is_published' => 0]);
        }

        foreach ($this->products() as $product) {
            $productId = $this->upsertProduct($product);
            $this->upsertProductVersion($productId, $product);
        }
    }

    private function updateSettings(): void
    {
        DB::table('site_settings')
            ->where('key', 'footer_tagline')
            ->update(['value' => 'Web development and technical solutions for businesses in Georgia and international clients.']);
    }

    private function upsertProduct(array $product): int
    {
        $existingBySlug = DB::table('digital_products')->where('slug', $product['slug'])->first();
        if ($existingBySlug) {
            DB::table('digital_products')->where('id', $existingBySlug->id)->update($this->productFields($product));

            return (int) $existingBySlug->id;
        }

        $preferred = DB::table('digital_products')->where('id', $product['preferred_id'])->first();
        if ($preferred && ! $this->productHasPurchases((int) $preferred->id) && in_array($preferred->name, ['1234', '3243'], true)) {
            DB::table('digital_products')->where('id', $preferred->id)->update($this->productFields($product));

            return (int) $preferred->id;
        }

        if (! $preferred) {
            DB::table('digital_products')->insert(array_merge(
                ['id' => $product['preferred_id'], 'created_at' => now(), 'user_id' => null],
                $this->productFields($product)
            ));

            return (int) $product['preferred_id'];
        }

        return (int) DB::table('digital_products')->insertGetId(array_merge(
            ['created_at' => now(), 'user_id' => null],
            $this->productFields($product)
        ));
    }

    private function upsertProductVersion(int $productId, array $product): void
    {
        $fields = [
            'version_number' => '1.0',
            'changelog' => 'Initial professional PDF resource.',
            'file_path' => $product['file_path'],
            'is_active' => 1,
            'digital_product_id' => $productId,
        ];

        $existing = DB::table('digital_product_versions')
            ->where('digital_product_id', $productId)
            ->where('file_path', $product['file_path'])
            ->first();

        if ($existing) {
            DB::table('digital_product_versions')->where('id', $existing->id)->update($fields);

            return;
        }

        $preferred = DB::table('digital_product_versions')->where('id', $product['preferred_version_id'])->first();
        if (! $preferred) {
            DB::table('digital_product_versions')->insert(array_merge(
                ['id' => $product['preferred_version_id'], 'created_at' => now()],
                $fields
            ));

            return;
        }

        DB::table('digital_product_versions')->insert(array_merge(['created_at' => now()], $fields));
    }

    private function productFields(array $product): array
    {
        return [
            'name' => $product['name'],
            'slug' => $product['slug'],
            'short_description' => $product['short_description'],
            'description' => $this->blockHtml($product['description']),
            'category' => $product['category'],
            'tags' => json_encode($product['tags']),
            'price' => $product['price'],
            'sale_price' => null,
            'sale_ends_at' => null,
            'image' => null,
            'cover_image' => null,
            'gallery_images' => json_encode([]),
            'demo_url' => null,
            'is_published' => 1,
            'is_featured' => $product['featured'] ? 1 : 0,
        ];
    }

    private function blockHtml(string $html): string
    {
        return str_replace(
            ['</p><', '</h2><', '</ul><'],
            ["</p>\n<", "</h2>\n<", "</ul>\n<"],
            $html
        );
    }

    private function productHasPurchases(int $productId): bool
    {
        return DB::table('purchases')
            ->join('digital_product_versions', 'digital_product_versions.id', '=', 'purchases.digital_product_version_id')
            ->where('digital_product_versions.digital_product_id', $productId)
            ->exists();
    }

    private function pages(): array
    {
        return [
            1 => [
                'seo_title' => 'Besarion Archvadze | Full-Stack Web Developer in Georgia',
                'seo_description' => 'Full-stack web development for businesses in Georgia and international clients. Business websites, Laravel/PHP applications, integrations, e-commerce and technical modernization.',
                'hero_title' => 'Websites and web systems built for real business.',
                'hero_subtitle' => 'I design, build and improve websites and web applications for businesses in Georgia and international clients - from professional company websites to custom systems, integrations and ongoing technical work.',
                'hero_button_text' => 'Start a Project',
                'services_title' => 'Web Development Services',
                'services_subtitle' => 'Practical development work for businesses that need a reliable website, a stronger application, or experienced technical support.',
                'features_title' => 'How I Approach Technical Work',
                'features_subtitle' => 'Clear scope, careful implementation, production-minded decisions and direct communication.',
                'portfolio_title' => 'Selected Work',
                'portfolio_subtitle' => 'Real project examples will be added as fuller case studies when they are ready.',
                'testimonials_title' => 'Client Feedback',
                'blog_title' => 'News & Insights',
                'blog_subtitle' => 'Practical notes on web development, Laravel, performance, search and maintaining reliable systems.',
            ],
            2 => [
                'page_title' => 'About Me',
                'page_subtitle' => 'Independent senior full-stack developer based in Georgia.',
                'hero_title' => 'About Besarion Archvadze',
                'hero_subtitle' => 'Full-stack development, practical technical planning and direct collaboration.',
                'content' => '<p>I&apos;m Besarion Archvadze, a full-stack web developer based in Georgia with more than 15 years of hands-on experience building and maintaining web applications.</p>' .
                    '<p>I work directly with businesses that need reliable technical delivery rather than a generic template. Depending on the project, that work can include planning, backend development, frontend implementation, database design, API integrations, deployment and ongoing maintenance.</p>' .
                    '<p>My main stack includes PHP, Laravel, Symfony, JavaScript, TypeScript and SQL databases, together with Linux and Docker-based production environments. The technology matters, but the real goal is a system that fits the business, is understandable to maintain and can be improved over time.</p>' .
                    '<p>For clients, direct communication is part of the value. You can discuss the actual business problem, the existing technical situation and the practical next step with the person doing the work.</p>',
                'seo_title' => 'About Besarion Archvadze | Full-Stack Developer',
                'seo_description' => 'About Besarion Archvadze, an independent full-stack developer in Georgia with 15+ years of hands-on web development experience.',
            ],
            3 => [
                'page_title' => 'Discuss Your Project',
                'page_subtitle' => 'Have a website, application or technical problem to discuss? Tell me what you need, what already exists and where the main problem is. I can help define a practical next step.',
                'hero_title' => 'Discuss Your Project',
                'hero_subtitle' => 'Share the context and I will help identify the practical technical next step.',
                'seo_title' => 'Discuss Your Web Project | Besarion Archvadze',
                'seo_description' => 'Discuss a website, Laravel/PHP application, integration, e-commerce project or technical modernization with Besarion Archvadze.',
            ],
            7 => [
                'title' => 'How I Work',
                'page_title' => 'How I Work',
                'page_subtitle' => 'A practical process for turning a business need into reliable web development work.',
                'content' => '<p>Good technical work starts with understanding the actual problem. Before choosing tools or estimating a build, I look at what the business needs, what already exists and what would create the most useful result.</p><h2>Understand the problem</h2><p>The first step is to clarify the goal, the audience, the current system and the constraints. A small website, an internal tool and a Laravel application upgrade all need different levels of planning.</p><h2>Define the scope</h2><p>Scope should be clear enough that both sides understand what is included, what is not included and which decisions still need confirmation. This prevents unnecessary work and avoids vague expectations.</p><h2>Choose practical architecture</h2><p>I prefer straightforward architecture that fits the project. Sometimes that means a simple CMS-driven website. Sometimes it means a custom backend, database structure, API integration or deployment workflow.</p><h2>Build, test and review</h2><p>Implementation should be reviewed against the original requirements, checked on real devices and tested around forms, payments, integrations, permissions and other business-critical flows.</p><h2>Launch and support</h2><p>A launch is not only uploading files. It includes SSL, backups, configuration, email, analytics, error handling and a plan for maintenance after the site or application goes live.</p>',
                'seo_title' => 'How I Work | Besarion Archvadze',
                'seo_description' => 'A practical web development process focused on clear scope, sensible architecture, testing, launch and ongoing support.',
            ],
            8 => [
                'page_title' => 'News & Insights',
                'page_subtitle' => 'Practical notes on web development, Laravel, performance, search and maintaining reliable systems.',
                'content' => '<p>Notes and analysis for businesses and teams that care about reliable web systems.</p>',
                'seo_title' => 'Web Development News & Insights | Besarion Archvadze',
                'seo_description' => 'Practical web development news and analysis covering Laravel, PHP, performance, search, integrations and production systems.',
            ],
            9 => [
                'page_title' => 'Selected Work',
                'page_subtitle' => 'Real project examples will be added as fuller case studies when they are ready.',
                'content' => '<p>Portfolio case studies are being prepared. The owner will add real projects and screenshots manually.</p>',
                'seo_title' => 'Web Development Portfolio | Besarion Archvadze',
                'seo_description' => 'Selected websites, web applications and technical projects by Besarion Archvadze. Real case studies will be added as project details are prepared.',
            ],
            10 => [
                'page_title' => 'Web Development Services',
                'page_subtitle' => 'Websites, Laravel/PHP applications, integrations, e-commerce workflows and technical support for businesses that need reliable implementation.',
                'seo_title' => 'Web Development Services | Besarion Archvadze',
                'seo_description' => 'Business websites, custom web applications, Laravel/PHP development, API integrations, e-commerce, maintenance and technical consulting.',
            ],
            11 => [
                'page_title' => 'Web Development Guides',
                'page_subtitle' => 'Practical guides for planning, improving and launching business websites and web applications.',
                'seo_title' => 'Web Development Guides | Besarion Archvadze',
                'seo_description' => 'Practical guides on planning business websites, Laravel applications, performance, hosting, production launch and technical modernization.',
            ],
            12 => [
                'page_title' => 'Web Development Resources',
                'page_subtitle' => 'Practical templates and checklists for planning websites, audits, launches, deployments and integrations.',
                'seo_title' => 'Web Development Resources & Templates | Archvadze.com',
                'seo_description' => 'Downloadable web development resources, planning workbooks and technical checklists for business websites, launches, audits and integrations.',
            ],
            13 => [
                'page_title' => 'Client Feedback',
                'page_subtitle' => 'Real client feedback will appear here when approved testimonials are available.',
            ],
            14 => [
                'page_title' => 'Frequently Asked Questions',
                'page_subtitle' => 'Concise answers about project scope, existing systems, maintenance, integrations and how work usually starts.',
                'content' => '<p>Answers to common questions about working with an independent full-stack developer.</p>',
            ],
            15 => [
                'title' => 'Working Principles',
                'page_title' => 'Working Principles',
                'page_subtitle' => 'The practical standards I try to keep in every project.',
                'content' => '<p>Professional web development is not only about producing screens. It is about making technical decisions that are clear, maintainable and appropriate for the business using the system.</p><h2>Clear communication</h2><p>I prefer direct discussion, written scope and realistic expectations. If something is uncertain, it should be identified early rather than hidden until delivery.</p><h2>No unnecessary rebuilds</h2><p>An existing website or application should be assessed before deciding to replace it. Sometimes modernization, refactoring or a focused integration is the better business decision.</p><h2>Maintainable code</h2><p>Code should be understandable enough to maintain, debug and improve later. Short-term shortcuts often become expensive when the business depends on the system.</p><h2>Security and privacy awareness</h2><p>Forms, accounts, payments, files and integrations need careful handling. Security is not a feature to add at the end; it affects implementation from the start.</p><h2>Realistic scope and direct responsibility</h2><p>Good projects work best when priorities are clear, responsibilities are understood and the technical work is matched to the real business need.</p>',
                'seo_title' => 'Working Principles | Besarion Archvadze',
                'seo_description' => 'Practical working principles for clear communication, maintainable code, realistic scope and responsible web development.',
            ],
        ];
    }

    private function services(): array
    {
        return [
            1 => ['name' => 'Business Websites', 'description' => 'Professional websites for companies that need to present their work clearly, build trust and generate serious enquiries. I focus on clear structure, reliable forms, sensible content management and a site that is easy to maintain after launch.'],
            2 => ['name' => 'Custom Web Applications', 'description' => 'Web applications built around a real business workflow rather than forcing the team into a generic template. This can include dashboards, approval flows, internal tools, reporting screens and customer-facing systems.'],
            3 => ['name' => 'Laravel & PHP Development', 'description' => 'New development, maintenance, bug fixing and gradual modernization for Laravel, Symfony and PHP applications. I can help extend an existing system, improve weak areas or build a focused backend from scratch.'],
            4 => ['name' => 'E-commerce Development', 'description' => 'Online sales workflows for products, services or digital resources, including product structure, customer accounts, checkout and payment integration. The goal is a store or payment flow that works reliably for both customers and administrators.'],
            5 => ['name' => 'API Integrations', 'description' => 'Connect payments, email platforms, external services and internal systems so information does not have to be moved manually. I pay attention to authentication, data mapping, error handling and what happens when an external service fails.'],
            6 => ['name' => 'Client Portals & Internal Tools', 'description' => 'Secure web interfaces for customers, staff or partners, including account areas, documents, project updates and internal administration. These systems work best when permissions, business rules and day-to-day workflows are considered from the beginning.'],
            7 => ['name' => 'Automation & AI Integration', 'description' => 'Automation and AI are useful when they remove repetitive work or improve an existing process. I help identify practical use cases, connect the required systems and keep human review where the business still needs judgment.'],
            8 => ['name' => 'Performance & Technical SEO', 'description' => 'Technical improvements for websites that are slow, hard to crawl or difficult to use on mobile devices. Work may include image handling, caching, frontend weight, metadata, page structure and Core Web Vitals basics.'],
            9 => ['name' => 'Maintenance & Modernization', 'description' => 'Improve an existing website or PHP application without rebuilding everything unnecessarily. I can review the codebase, fix bugs, upgrade dependencies, reduce technical debt and plan gradual improvements around business risk.'],
            10 => ['name' => 'Technical Consulting & Support', 'description' => 'Review an existing application, identify the actual problem and define the smallest sensible technical solution before committing to a rebuild. This is useful when a business needs senior technical judgment, troubleshooting or support for an internal team.'],
        ];
    }

    private function features(): array
    {
        return [
            1 => ['name' => 'Clear Project Scope', 'description' => 'Define the goal, users, required pages, functionality and constraints before development starts.'],
            2 => ['name' => 'Practical Technical Planning', 'description' => 'Choose an approach that fits the business need instead of adding complexity for its own sake.'],
            3 => ['name' => 'Responsive Implementation', 'description' => 'Build interfaces that work cleanly across desktop, tablet and mobile use cases.'],
            4 => ['name' => 'Backend & Database Development', 'description' => 'Design reliable application logic, database structures and administrative workflows behind the public interface.'],
            5 => ['name' => 'API & Service Integrations', 'description' => 'Connect payments, email, analytics and external platforms with clear data flow and error handling.'],
            6 => ['name' => 'Secure Production Setup', 'description' => 'Prepare SSL, configuration, permissions, backups and deployment details before launch.'],
            7 => ['name' => 'Performance-Conscious Delivery', 'description' => 'Keep pages and workflows efficient by considering hosting, caching, images and frontend weight.'],
            8 => ['name' => 'SEO-Ready Foundations', 'description' => 'Use clear page structure, metadata and crawlable content so search engines can understand the site.'],
            9 => ['name' => 'Maintainable Code', 'description' => 'Prefer code and architecture that can be debugged, extended and handed over without unnecessary confusion.'],
            10 => ['name' => 'Testing Before Launch', 'description' => 'Check critical forms, permissions, payments, integrations, mobile layouts and content before going live.'],
            11 => ['name' => 'Ongoing Technical Support', 'description' => 'Provide continued help for fixes, updates, monitoring, improvements and practical technical questions after launch.'],
        ];
    }

    private function faq(): array
    {
        return [
            1 => ['question' => 'Do you work only with clients in Georgia?', 'answer' => 'No. I am based in Georgia and work with Georgian businesses, but I can also work with international clients and agencies when the project is a good technical fit.'],
            2 => ['question' => 'What types of projects do you take on?', 'answer' => 'I work on business websites, Laravel/PHP applications, custom web systems, integrations, e-commerce workflows, technical modernization and ongoing maintenance.'],
            3 => ['question' => 'Can you work with an existing website or application?', 'answer' => 'Yes. I can review an existing system, fix specific problems, add features, improve performance or plan a gradual modernization instead of rebuilding everything immediately.'],
            4 => ['question' => 'How does a project usually start?', 'answer' => 'A project usually starts with a clear discussion of the business goal, current situation, required functionality, timeline and budget range. From there we can define the practical next step.'],
            5 => ['question' => 'Do you work with existing development teams?', 'answer' => 'Yes. I can support agencies or internal teams with PHP, Laravel, backend work, integrations, technical review or production troubleshooting.'],
            6 => ['question' => 'Can you integrate payments or external APIs?', 'answer' => 'Yes. I can integrate payment providers, email services, analytics tools, third-party APIs and internal systems, with attention to authentication, data mapping and failure handling.'],
            7 => ['question' => 'Do you provide ongoing maintenance?', 'answer' => 'Yes. Maintenance can include updates, bug fixes, monitoring, performance improvements, small feature work and technical support after launch.'],
            8 => ['question' => 'Do you provide hosting?', 'answer' => 'I can help choose, configure and deploy to suitable hosting, VPS, cloud or managed platforms. Hosting ownership and billing should usually stay with the client.'],
            9 => ['question' => 'Can you improve a Laravel or PHP application instead of rebuilding it?', 'answer' => 'Often, yes. The right decision depends on code quality, business risk, dependencies, security issues and how much the current system still supports the business.'],
            10 => ['question' => 'What information should I send before requesting a quote?', 'answer' => 'Send the business goal, existing website or system details, required features, integrations, timeline, budget range and any examples or documents that explain the workflow.'],
        ];
    }

    private function publications(): array
    {
        return [
            1 => $this->publication('Laravel 13: What the New Release Means for Business Applications', 'laravel-13-business-applications', 'Laravel 13 brings useful platform improvements, but businesses should upgrade for stability, maintainability and long-term support - not just because a new version exists.', '<p>Laravel 13 was released on March 17, 2026. For developers, a new major Laravel release is always interesting. For a business running a production application, the better question is more practical: should we upgrade now, plan it carefully, or wait until the application is ready?</p><p>The release continues Laravel&apos;s focus on productive application development and modern PHP support, including PHP 8.3 through PHP 8.5. It also introduces or expands first-party capabilities around AI primitives, JSON:API resources, semantic and vector search, and ongoing improvements across queues, cache and security-related foundations.</p><h2>Why this matters for business systems</h2><p>Most business applications are not upgraded because a release note sounds exciting. They are upgraded because the current system needs security support, better maintainability, compatibility with dependencies, or new features that would otherwise be difficult to build cleanly.</p><p>Laravel&apos;s newer tools can matter when an application needs structured APIs, better search behavior, background processing, or a cleaner path for integrating AI-assisted features. But those benefits only matter if they solve a real problem in the business workflow.</p><h2>When upgrading makes sense</h2><p>An upgrade is worth planning when the current application is close to unsupported PHP or Laravel versions, when packages are becoming difficult to maintain, when the team needs newer framework features, or when development has slowed because the codebase is carrying too much old structure.</p><p>For a Laravel application that handles payments, customer records, orders, documents, internal workflows or integrations, the upgrade should include testing. The important flows should be checked before and after the work: login, permissions, forms, emails, payments, queues, file uploads, exports and third-party API calls.</p><h2>When stability matters more</h2><p>Some systems should not move immediately. If the application is active, poorly tested, or dependent on older packages, the safest first step may be an assessment. A careful developer can identify blockers, check dependency compatibility and prepare a staged upgrade plan.</p><p>The goal is not to chase the newest version. The goal is to keep the application secure, understandable and practical to improve. Laravel 13 gives teams more capability, but a good upgrade decision still starts with the business risk and the current state of the codebase.</p>'),
            2 => $this->publication('Managed Queues on Laravel Cloud: Why Background Processing Matters', 'managed-queues-laravel-cloud-background-processing', 'Background jobs are not only a developer convenience. They help business applications process slow, repeated or failure-prone work without blocking the user.', '<p>Laravel announced Managed Queues for Laravel Cloud in August 2026. The feature is important because queue workers can autoscale based on pressure, failed jobs have built-in visibility, and workers can scale down when idle.</p><p>For a business owner, that might sound like infrastructure language. The practical meaning is simpler: the application can do slow work in the background while the customer or staff member continues using the system.</p><h2>What belongs in a queue</h2><p>Many web applications need to handle tasks that should not block a request. Examples include sending emails, importing spreadsheets, exporting reports, processing images, syncing with external APIs, generating invoices, updating search indexes or sending notifications.</p><p>If these jobs happen during a normal page request, users wait longer and errors are harder to recover from. If they run in the background, the application can respond quickly while workers handle the heavier task separately.</p><h2>Why managed queues help</h2><p>Running queue workers manually is possible, but it creates operational responsibility. Someone has to keep workers alive, scale them when work increases, monitor failures and reduce unnecessary capacity when the system is quiet.</p><p>Managed queues reduce that operational burden. Autoscaling helps during busy periods, while scale-down behavior avoids running more workers than needed. Visibility into failed jobs is also important because integrations and background tasks do fail in real systems.</p><h2>Where this improves business workflows</h2><p>An e-commerce site can queue order emails and inventory sync. A clinic or service company can queue report generation. A hospitality business can queue booking notifications and external calendar updates. An agency dashboard can queue client imports and exports.</p><p>The result is not magic. It is a more reliable way to handle work that takes time, depends on external systems, or needs retry logic. For Laravel applications that already use queues, managed infrastructure can make production operation easier. For applications that do not use queues yet, it may be a good time to review which tasks should move out of the request cycle.</p>'),
            3 => $this->publication('What Laravel Announced at Laracon US 2026', 'laracon-us-2026-laravel-announcements', 'Laravel LSP, Inertia DevTools, Managed Queues and scale-to-zero improvements all point toward a more productive and production-aware Laravel ecosystem.', '<p>Laracon US 2026 included several announcements that matter to developers maintaining real applications, not only to people starting new projects. The most useful updates are the ones that improve feedback, deployment, debugging and production operation.</p><h2>Laravel LSP</h2><p>Laravel LSP is important because editor support can reduce friction in day-to-day development. Better framework-aware completion and understanding helps developers move around a Laravel codebase with more confidence, especially on larger applications with routes, models, configuration and custom conventions.</p><h2>Inertia DevTools</h2><p>Inertia is widely used when teams want a modern frontend experience without turning the backend and frontend into completely separate products. DevTools for Inertia can help developers inspect page props, requests and client-side behavior more clearly, which matters when debugging production-like workflows.</p><h2>Managed Queues</h2><p>Managed Queues on Laravel Cloud are relevant because many business systems depend on background jobs. Emails, imports, exports, reports, media processing and integrations all become easier to operate when workers can scale and failures are visible.</p><h2>Scale-to-zero developments</h2><p>Scale-to-zero is attractive for lower-traffic applications, internal tools and seasonal workloads because infrastructure can become more cost-conscious when idle. It is not the right answer for every system, but it gives teams more deployment options.</p><h2>What actually matters</h2><p>The announcement list is less important than the direction. Laravel is continuing to invest in developer experience and production operations. For businesses using Laravel, the practical benefit is not a single feature. It is a framework ecosystem that keeps making common web application work more manageable.</p>'),
            4 => $this->publication('Laravel Cloud Now Supports Next.js and Nuxt Deployments', 'laravel-cloud-nextjs-nuxt-deployments', 'Support for Next.js and Nuxt deployments gives teams more frontend options, but a separate frontend is still a tradeoff, not an automatic upgrade.', '<p>Laravel announced support for Next.js and Nuxt deployments on Laravel Cloud in July 2026. This is useful for teams that want Laravel for the backend and a JavaScript framework for the frontend, while keeping deployment closer to the Laravel ecosystem.</p><p>For some projects, that architecture makes sense. A public marketing site, a complex application interface, a content-heavy frontend or a team with dedicated frontend developers may benefit from Next.js or Nuxt. The frontend can move independently while Laravel handles the API, database, queues, authentication and business logic.</p><h2>The tradeoff</h2><p>A separate frontend and backend can also add complexity. There may be two build systems, two deployment concerns, API contracts to maintain, authentication details to handle, duplicated validation concerns and more moving parts during debugging.</p><p>That complexity can be worth it, but it should be chosen for a reason. If the project is a business website, a client portal, an internal dashboard or an application where Laravel Blade or Inertia would be simpler, a traditional Laravel approach may still be the better business decision.</p><h2>When separate frontend architecture helps</h2><p>It helps when the frontend has a lot of independent behavior, when SEO requirements benefit from a specific rendering strategy, when the team already works in a JavaScript framework, or when multiple clients need to consume the same backend API.</p><h2>When simpler is better</h2><p>Simpler architecture is often better when the business needs reliability, maintainability and a clear path to launch. A well-built Laravel application can still handle content, forms, payments, portals, admin screens and integrations without splitting the stack unnecessarily.</p><p>The new deployment support is a welcome option. The important decision remains the same: choose the architecture that fits the project, the team and the long-term maintenance responsibility.</p>'),
            5 => $this->publication('Search Is Changing: What Businesses Should Know About AI Visibility', 'search-ai-visibility-business-websites', 'AI features in search do not remove the need for useful original content, clean technical structure and careful measurement.', '<p>Search is changing as generative AI features become part of how people discover and compare information. For businesses, the wrong response is to publish large amounts of generic AI text and hope it ranks. The better response is to make the website more useful, more structured and easier to understand.</p><h2>Useful original content still matters</h2><p>Google Search Central continues to emphasize helpful, reliable, people-first content. A business website should explain what the company does, who it serves, what problems it solves and why a visitor should trust it. Thin generic pages are not a strong foundation.</p><p>For a Georgian business, this can mean clear service pages, real project examples, practical explanations, local context, accurate contact information and content that reflects how customers actually ask questions.</p><h2>Technical structure matters</h2><p>AI-assisted search still depends on content that can be crawled, interpreted and trusted. Page titles, headings, internal links, structured content, fast mobile pages, accessible markup and clear metadata all help search systems understand the site.</p><h2>Measurement is evolving</h2><p>Search Console and search reporting are evolving as AI search visibility becomes more important. Businesses should expect measurement to change over time, but the practical work remains familiar: maintain the site, publish useful content, watch queries and improve weak pages.</p><h2>Avoid mass generic AI content</h2><p>AI tools can help draft or organize content, but publishing generic pages at scale is risky and usually unhelpful to real visitors. A business should use content to answer genuine customer questions, explain real services and support decision-making.</p><p>There are no guaranteed rankings. The best long-term approach is still a technically sound website with clear, useful and original content.</p>'),
            6 => $this->publication('PHP 8.5 in 2026: Maintenance Matters More Than Version Chasing', 'php-85-maintenance-version-chasing', 'PHP upgrades should be planned around security, compatibility, testing and business risk - not only around the newest version number.', '<p>PHP 8.5 is a current supported branch in 2026, and PHP 8.5.8 was released on July 2, 2026. For businesses running PHP or Laravel applications, the important lesson is not to chase versions for appearances. It is to keep the application maintainable and supported.</p><h2>Why version maintenance matters</h2><p>Old PHP versions eventually stop receiving active support and security fixes. When that happens, hosting options become narrower, packages become harder to update and developers spend more time working around old constraints.</p><p>A planned upgrade keeps the business in a healthier position. It reduces risk, improves compatibility and makes future development easier.</p><h2>Compatibility comes first</h2><p>Before upgrading PHP, a developer should check the framework version, Composer dependencies, server extensions, deployment environment and automated or manual tests. A PHP upgrade can be straightforward, but only if the application and packages are ready.</p><h2>Testing is not optional</h2><p>Critical flows should be checked after an upgrade: login, forms, payments, emails, file uploads, scheduled jobs, queues, API integrations and admin actions. The goal is to find issues before customers or staff do.</p><h2>A sensible upgrade plan</h2><p>For older applications, the best approach may be staged. First fix dependency constraints, then update framework versions, then move PHP versions, then clean up deprecated code. This is often safer than trying to modernize everything in one large change.</p><p>Version numbers matter, but maintenance discipline matters more. A business application should be secure, testable and practical to improve over time.</p>'),
        ];
    }

    private function publication(string $title, string $slug, string $excerpt, string $content): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'excerpt' => $excerpt,
            'content' => $content,
            'status' => 'published',
            'is_published' => 1,
            'published_at' => '2026-08-20 12:00:00',
            'cover_image' => null,
        ];
    }

    private function guides(): array
    {
        return [
            1 => $this->guide('Planning a Business Website Before Development Starts', 'planning-business-website-before-development', '<p>A business website project goes better when the important decisions are made before design and development begin. This does not mean producing a heavy technical document. It means clarifying what the website must achieve, who it is for and what information the developer needs to build it properly.</p><h2>Start with the business goal</h2><p>Before discussing layout or technology, define the reason for the website. A small professional service company may need trust, clear enquiries and service explanations. A hospitality business may need bookings, location information and seasonal content. A clinic may need clear service pages, contact details and careful handling of sensitive expectations.</p><p>The goal affects every later decision. If the website should generate enquiries, the content, forms and calls to action need to support that. If it should support existing clients, the structure may need documents, account access or support information.</p><h2>Define the audience</h2><p>Write down who the website is speaking to. Local customers, international clients, business owners, procurement managers and technical partners all read differently. A good website does not try to impress everyone with vague language. It explains the right information clearly to the people most likely to become serious clients.</p><h2>Prepare content before build</h2><p>Most website delays are content delays. Prepare service names, descriptions, company information, contact details, legal text, images and any proof that can be shown publicly. If real portfolio or case study material is not ready, it is better to say that clearly than publish fake examples.</p><h2>List required functionality</h2><p>Separate normal pages from actual functionality. A contact form, newsletter, booking request, payment flow, account area, admin panel or integration changes the scope. Each feature should have a short explanation of what the user does, what the business receives and what happens after submission.</p><h2>Think about integrations</h2><p>If the website must connect to email marketing, CRM, payments, analytics, booking tools or internal systems, identify those early. Integrations often require accounts, API keys, testing access and decisions about ownership.</p><h2>Budget and scope</h2><p>A realistic budget helps a developer recommend the right approach. A simple CMS website, a custom Laravel application and a full customer portal are not the same type of project. Clear priorities make it possible to build the most useful first version without pretending every idea belongs in the first release.</p><h2>Domain, hosting and ownership</h2><p>The business should know who owns the domain, hosting account, email service, analytics account and source code. Keeping ownership clear prevents problems later when the site needs maintenance or a new developer has to help.</p><h2>A practical starting package</h2><p>Before requesting a quote, prepare the business goal, audience, required pages, must-have features, integrations, examples, timeline and budget range. This gives the project a much better start and reduces the risk of misunderstandings.</p><p>If you are preparing a serious website or application, a short planning discussion can often save more time than rushing into design immediately.</p>'),
            2 => $this->guide('When to Modernize an Existing PHP Application Instead of Rebuilding It', 'modernize-existing-php-application-instead-of-rebuild', '<p>Many businesses eventually become unhappy with an old PHP application. It may be slow, difficult to change, poorly documented or dependent on old hosting. The first instinct is often to rebuild everything. Sometimes that is correct, but often modernization is a better first step.</p><h2>Rebuilds carry business risk</h2><p>A full rebuild sounds clean, but it can take longer than expected because the existing system usually contains years of business rules. Some of those rules are not written anywhere. Staff simply know how the system behaves, and customers may depend on details that are easy to miss.</p><p>If the old application still supports important operations, replacing it all at once can interrupt the business. A modernization plan can reduce that risk by improving the system gradually.</p><h2>Look at the actual problems</h2><p>The right decision starts with diagnosis. Is the application insecure? Is the code impossible to modify? Are dependencies unsupported? Is performance poor because of database queries, hosting, images or frontend weight? Are users asking for new workflows that the current structure cannot support?</p><p>Different problems need different responses. A slow page may need database work, not a rebuild. An old payment integration may need replacement. A fragile admin panel may need focused refactoring.</p><h2>Gradual upgrades</h2><p>Modernization can include upgrading PHP, updating Laravel or Symfony, replacing abandoned packages, improving deployment, adding tests, cleaning up database queries and moving risky logic into clearer services. These steps make future development safer.</p><h2>APIs and integrations</h2><p>Older applications often need to connect to newer tools: payment providers, CRM systems, email platforms, reporting dashboards or mobile apps. Adding a well-designed API or integration layer may provide real business value without replacing the entire system.</p><h2>Testing before changing</h2><p>Before changing important code, identify critical workflows and test them. Login, permissions, orders, payments, emails, exports and file uploads should be checked. Even a small test suite can make modernization less risky.</p><h2>When rebuilding is justified</h2><p>A rebuild may be the right choice when the old application no longer matches the business, cannot be secured, depends on unsupported infrastructure or blocks every meaningful improvement. Even then, the old system should be studied carefully so important behavior is not lost.</p><h2>Choose the smallest sensible step</h2><p>The best technical decision is not always the biggest one. A careful assessment can identify whether the business needs a full rebuild, a staged modernization or a focused fix. That decision should be based on risk, value and maintainability.</p>'),
            3 => $this->guide('A Practical Website Performance Checklist for Business Owners', 'practical-website-performance-checklist-business-owners', '<p>Website performance affects enquiries, sales and trust. Visitors may not know why a page feels slow, but they notice when it takes too long to load or becomes difficult to use on a phone. Business owners do not need to understand every technical detail, but they should know the main areas that influence speed.</p><h2>Start with hosting</h2><p>Cheap or overloaded hosting can make even a simple website feel slow. The right hosting depends on the site: a brochure website, an online shop and a Laravel application have different needs. Check server response time, PHP version, database performance and whether backups are included.</p><h2>Review image size</h2><p>Large images are one of the most common performance problems. Photos should be resized for the web, compressed properly and served in suitable formats. A homepage should not load huge original camera files when visitors only need a carefully sized image.</p><h2>Use caching where appropriate</h2><p>Caching can reduce repeated work. A CMS page, service listing or blog index may not need to be rebuilt from the database on every request. For Laravel applications, caching can include configuration, routes, views and selected data. Caching should be planned so updates still appear correctly.</p><h2>Watch JavaScript weight</h2><p>Too much JavaScript can slow down mobile devices. Sliders, tracking scripts, chat widgets, animation libraries and unused frontend bundles all add weight. A professional site should use scripts because they support a real feature, not because they look impressive in a template.</p><h2>Check Core Web Vitals</h2><p>Core Web Vitals are Google&apos;s user experience metrics around loading, responsiveness and visual stability. They are not the only performance measure, but they are useful because they focus on what visitors experience. Problems often come from large media, render-blocking assets, layout shifts and slow server responses.</p><h2>Test mobile first</h2><p>Many business sites look acceptable on a fast desktop connection but feel poor on mobile. Test important pages on a phone, not only in a desktop browser. Forms, menus, images and buttons should all remain usable.</p><h2>Do not forget content and conversion</h2><p>A fast website with unclear content still performs badly as a business tool. Performance work should support the visitor&apos;s path: understand the offer, trust the company, submit a form, buy a product or contact the business.</p><h2>A practical first audit</h2><p>Check hosting, image sizes, caching, JavaScript, mobile behavior, important forms and analytics. Then fix the problems that have the highest impact. Performance improvement is most useful when it is connected to business goals, not treated as a score-chasing exercise.</p>'),
            4 => $this->guide('Preparing a Website for Production Launch', 'preparing-website-production-launch', '<p>A website launch should be treated as a production event, not only as the moment when files are uploaded. A careful launch reduces avoidable problems and helps the business start from a stable position.</p><h2>SSL and domain setup</h2><p>The site should load over HTTPS, redirect consistently and use the correct domain version. DNS records should be checked before launch, especially if email services or third-party verification records are connected to the same domain.</p><h2>Backups and recovery</h2><p>Backups should exist before launch, and someone should know how to restore them. For CMS and application sites, both files and database content matter. A backup that has never been tested is only an assumption.</p><h2>Email and forms</h2><p>Contact forms, order emails, password resets and notifications should be tested with real addresses. Email configuration is a common launch problem. SPF, DKIM and delivery settings may be needed depending on the email provider.</p><h2>Analytics and measurement</h2><p>Analytics should be configured before launch if the business wants to measure traffic and enquiries. Important form submissions, product views, purchases or contact actions may need event tracking. Measurement should respect privacy and legal obligations.</p><h2>Error handling and logging</h2><p>Production systems need error visibility. A blank error page or silent failure can hide serious problems. Laravel applications should have sensible logging, correct environment settings and a way for technical issues to be investigated.</p><h2>Forms and business flows</h2><p>Every important form should be tested: contact, newsletter, checkout, login, registration, profile update and file upload if present. Validation messages should be understandable, and submitted data should arrive where the business expects it.</p><h2>SEO basics</h2><p>Check page titles, meta descriptions, headings, canonical URLs, sitemap, robots settings and redirects from old URLs if the website is replacing another site. Search engines need clear structure, but users also benefit from well-organized pages.</p><h2>Security and permissions</h2><p>Admin access, user roles, file permissions, exposed debug settings and private downloads should be checked. The production environment should not expose development details or test credentials.</p><h2>Smoke testing after launch</h2><p>After DNS and deployment are complete, test the site again from the public URL. Check the homepage, main navigation, forms, login, checkout or order flow, mobile layout and important content pages.</p><p>A good launch checklist is not bureaucracy. It protects the business from simple mistakes that can create real operational problems.</p>'),
            5 => $this->guide('Choosing Hosting for a Laravel or Business Web Application', 'choosing-hosting-laravel-business-web-application', '<p>Hosting affects performance, reliability, maintenance and future development. The best option depends on what the website or application actually does. A simple company website, a Laravel application, an e-commerce site and an internal portal do not all need the same environment.</p><h2>Shared hosting</h2><p>Shared hosting can work for simple websites with modest traffic and limited technical needs. It is usually cheaper, but it may restrict deployment, PHP versions, background jobs, queues, SSH access and performance tuning. For custom Laravel applications, shared hosting is often limiting.</p><h2>VPS or cloud server</h2><p>A VPS or cloud server gives more control over PHP, database, queues, caching, file permissions and deployment. It can be a good fit for Laravel applications and custom systems, but it also creates maintenance responsibility. Someone must manage updates, backups, monitoring and security.</p><h2>Managed hosting</h2><p>Managed hosting can reduce operational work by handling server configuration, backups, scaling and monitoring. It may cost more, but the right managed platform can be worthwhile when the business depends on the application and does not want to manage infrastructure directly.</p><h2>Database considerations</h2><p>The database is often the most important part of a business application. Check backup options, storage limits, performance, access control and restore procedures. A site can be redeployed from code, but lost business data is a much bigger problem.</p><h2>Backups and restore plans</h2><p>Hosting should include a realistic backup strategy. For a Laravel application, this usually means database backups, uploaded file backups and enough retention to recover from mistakes that are discovered late.</p><h2>Scalability</h2><p>Not every business needs complex scaling. Many applications need stable hosting more than advanced infrastructure. Still, it is useful to know whether the environment can handle growth, heavier background jobs or more traffic later.</p><h2>Maintenance responsibility</h2><p>The business should know who is responsible for server updates, SSL renewal, backups, deployment, logs and incident response. Hosting is not only a place to put files. It is part of the production system.</p><h2>Choose based on risk</h2><p>If the website is mostly informational, keep hosting simple. If it handles payments, customer accounts, files, bookings or internal operations, choose an environment that can be maintained properly. The right hosting decision should match business risk, not only monthly price.</p>'),
        ];
    }

    private function guide(string $title, string $slug, string $content): array
    {
        return [
            'title' => $title,
            'slug' => $slug,
            'content' => $content,
            'youtube_url' => null,
            'cover_image' => null,
            'published_at' => '2026-08-20 12:00:00',
        ];
    }

    private function products(): array
    {
        return [
            [
                'preferred_id' => 12,
                'preferred_version_id' => 4,
                'name' => 'Business Website Project Brief',
                'slug' => 'business-website-project-brief',
                'short_description' => 'A structured workbook for preparing website requirements before hiring a developer.',
                'description' => '<p>A practical project brief workbook for business owners who want to prepare a website project clearly before development starts.</p><h2>Who it is for</h2><p>Companies, clinics, hospitality businesses, professional service firms and startups preparing a new website or a major website improvement.</p><h2>What is included</h2><ul><li>Business goals and audience prompts</li><li>Required page planning</li><li>Functionality and integration checklist</li><li>Content ownership questions</li><li>Budget, timeline and launch preparation prompts</li></ul><p>The workbook helps reduce unclear scope and missed decisions before a developer estimates or starts the project.</p>',
                'category' => 'planning-workbooks',
                'tags' => ['Website Planning', 'Project Brief', 'Business'],
                'price' => '9.00',
                'featured' => false,
                'file_path' => 'products/files/business-website-project-brief-2026-08-20.pdf',
            ],
            [
                'preferred_id' => 14,
                'preferred_version_id' => 5,
                'name' => 'Website Launch Checklist',
                'slug' => 'website-launch-checklist',
                'short_description' => 'A practical pre-launch checklist for reducing missed steps before a website goes live.',
                'description' => '<p>A concise launch checklist for business websites, service websites and small web applications.</p><h2>Who it is for</h2><p>Business owners, project managers and small teams preparing to publish a new or rebuilt website.</p><h2>What is included</h2><ul><li>Domain, DNS and SSL checks</li><li>Forms, email and analytics checks</li><li>Metadata, redirects and performance review</li><li>Accessibility and mobile testing basics</li><li>Backup and recovery reminders</li></ul><p>The checklist is designed to make launch preparation more systematic without turning it into an enterprise process.</p>',
                'category' => 'checklists',
                'tags' => ['Launch', 'Checklist', 'Website'],
                'price' => '7.00',
                'featured' => true,
                'file_path' => 'products/files/website-launch-checklist-2026-08-20.pdf',
            ],
            [
                'preferred_id' => 16,
                'preferred_version_id' => 6,
                'name' => 'Technical Website Audit Checklist',
                'slug' => 'technical-website-audit-checklist',
                'short_description' => 'A structured worksheet for reviewing technical SEO, performance, security and conversion basics.',
                'description' => '<p>A practical audit checklist for reviewing the technical health of an existing business website.</p><h2>Who it is for</h2><p>Business owners, marketing teams and developers who need a structured way to identify website issues before planning improvements.</p><h2>What is included</h2><ul><li>Server and domain review</li><li>Technical SEO prompts</li><li>Performance and mobile checks</li><li>Security and accessibility basics</li><li>Analytics and conversion flow review</li></ul><p>The checklist helps organize findings before deciding whether the site needs small fixes, deeper technical work or a wider rebuild.</p>',
                'category' => 'checklists',
                'tags' => ['Audit', 'Technical SEO', 'Performance'],
                'price' => '9.00',
                'featured' => false,
                'file_path' => 'products/files/technical-website-audit-checklist-2026-08-20.pdf',
            ],
            [
                'preferred_id' => 17,
                'preferred_version_id' => 7,
                'name' => 'Laravel Production Deployment Checklist',
                'slug' => 'laravel-production-deployment-checklist',
                'short_description' => 'A deployment checklist for Laravel applications moving into production.',
                'description' => '<p>A technical checklist for developers and teams preparing a Laravel application for production deployment.</p><h2>Who it is for</h2><p>Developers, agencies and small technical teams who want a structured review before deploying or updating a Laravel application.</p><h2>What is included</h2><ul><li>Environment and secrets review</li><li>Dependencies, migrations and caches</li><li>Queues, storage and permissions</li><li>Backups and rollback preparation</li><li>Post-deploy smoke testing</li></ul><p>The checklist is intended to reduce preventable production mistakes and make deployment responsibilities clearer.</p>',
                'category' => 'developer-resources',
                'tags' => ['Laravel', 'Deployment', 'Production'],
                'price' => '12.00',
                'featured' => false,
                'file_path' => 'products/files/laravel-production-deployment-checklist-2026-08-20.pdf',
            ],
            [
                'preferred_id' => 18,
                'preferred_version_id' => 8,
                'name' => 'API Integration Planning Workbook',
                'slug' => 'api-integration-planning-workbook',
                'short_description' => 'A planning workbook for defining API integrations before implementation starts.',
                'description' => '<p>A structured workbook for teams preparing an API integration between business systems.</p><h2>Who it is for</h2><p>Businesses, agencies and technical teams planning payment, CRM, email, booking, reporting or internal system integrations.</p><h2>What is included</h2><ul><li>Systems and ownership mapping</li><li>Authentication and access questions</li><li>Data mapping prompts</li><li>Error handling, retries and webhook planning</li><li>Testing and monitoring checklist</li></ul><p>The workbook helps clarify requirements before code is written, especially where multiple systems and responsibilities are involved.</p>',
                'category' => 'planning-workbooks',
                'tags' => ['API', 'Integrations', 'Planning'],
                'price' => '9.00',
                'featured' => false,
                'file_path' => 'products/files/api-integration-planning-workbook-2026-08-20.pdf',
            ],
        ];
    }
}
