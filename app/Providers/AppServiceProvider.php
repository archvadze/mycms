<?php

namespace App\Providers;

use App\Models\SiteSetting;
use App\Models\MenuItem;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\ServiceProvider;
use App\Models\Page;
use App\Models\Order;
use App\Models\Publication;
use App\Models\Service;
use App\Models\Testimonial;
use App\Models\Feature;
use App\Observers\OrderObserver;
use App\Observers\PublicationObserver;
use App\Observers\PortfolioProjectObserver;
use App\Observers\GuideObserver;
use App\Observers\ProjectObserver;
use App\Observers\DigitalProductObserver;
use App\Models\DigitalProduct;
use App\Models\Project;
use App\Models\Guide;
use App\Models\PortfolioProject;
use Illuminate\Validation\Rules\Password;
use Illuminate\Auth\Events\Login;
use App\Listeners\LogSuccessfulLogin;
use Illuminate\Support\Facades\Event;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            if (!request()->route()) return;
            $slug = request()->route()->getName();
            $slugMap = [
                'about' => 'about',
                'contact' => 'contact',
                'blog' => 'blog',
                'blog.show' => 'blog',
                'portfolio' => 'portfolio',
                'services' => 'services',
                'guides' => 'guides',
                'guides.show' => 'guides',
                'shop.index' => 'shop',
                'shop.show' => 'shop',
                'testimonials' => 'testimonials',
                'faq' => 'faq',
                'privacy-policy' => 'privacy-policy',
                'terms' => 'terms',
                'page.show' => request()->route('slug'),
            ];
            if (isset($slugMap[$slug])) {
                $pageSlug = $slugMap[$slug];
                if (!$view->offsetExists('page') || is_null($view->offsetGet('page'))) {
                    $page = \Illuminate\Support\Facades\Cache::remember(
                        'page.' . $pageSlug,
                        3600,
                        fn() => Page::where('slug', $pageSlug)->first()
                    );
                    $view->with('page', $page);
                }
            }
        });
        Publication::observe(PublicationObserver::class);
        PortfolioProject::observe(PortfolioProjectObserver::class);
        Guide::observe(GuideObserver::class);
        Project::observe(ProjectObserver::class);
        DigitalProduct::observe(DigitalProductObserver::class);

        Service::saved(fn() => Cache::forget('home.services'));
        Service::deleted(fn() => Cache::forget('home.services'));
        Testimonial::saved(fn() => Cache::forget('home.testimonials'));
        Testimonial::deleted(fn() => Cache::forget('home.testimonials'));
        Feature::saved(fn() => Cache::forget('home.features'));
        Feature::deleted(fn() => Cache::forget('home.features'));
        SiteSetting::saved(fn() => Cache::forget('site.settings'));
        MenuItem::saved(fn() => Cache::forget('menu.items'));
        MenuItem::deleted(fn() => Cache::forget('menu.items'));
        Page::saved(function ($page) {
            Cache::forget('page.' . $page->slug);
            if ($page->slug === 'home') {
                Cache::forget('home.page');
                Cache::forget('home.services');
                Cache::forget('home.projects');
                Cache::forget('home.publications');
                Cache::forget('home.testimonials');
                // items_count-based cache keys
                foreach (range(1, 20) as $i) {
                    Cache::forget('home.services.' . $i);
                    Cache::forget('home.projects.' . $i);
                    Cache::forget('home.publications.' . $i);
                    Cache::forget('home.testimonials.' . $i);
                }
            }
        });

        Event::listen(Login::class, LogSuccessfulLogin::class);

        Password::defaults(function () {
            return Password::min(8)
                ->mixedCase()
                ->numbers();
        });

        // Filament admin routes-ზე composer არ გაეშვება
        \Illuminate\Support\Facades\View::composer('*', function ($view) {
            if (Request::is('admin*')) {
                return;
            }

            $siteSettings = Cache::remember('site.settings', 3600, function () {
                return SiteSetting::pluck('value', 'key')->toArray();
            });

            $menuItems = Cache::remember('menu.items', 3600, function () {
                return MenuItem::where('is_active', true)->get();
            });

            $view->with('siteSettings', $siteSettings);
            $view->with('headerMenuItems', $menuItems->where('location', 'header')->values());
            $view->with('footerMenuItems', $menuItems->where('location', 'footer')->values());
            $view->with('bottomMenuItems', $menuItems->where('location', 'bottom')->values());
        });

        

         
    }
}
