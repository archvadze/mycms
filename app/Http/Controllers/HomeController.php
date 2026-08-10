<?php
namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Service;
use App\Models\PortfolioProject;
use App\Models\Testimonial;
use App\Models\Page;
use App\Models\Feature;
use App\Support\HomepageCache;
use Illuminate\Support\Facades\Cache;
use App\Models\SiteSetting;

class HomeController extends Controller
{
    public function index()
    {
        $homePage = Cache::remember('home.page', 3600, function () {
            return Page::where('slug', 'home')->first();
        });

        $modules = Cache::remember('site.settings', 3600, fn() => SiteSetting::pluck('value', 'key')->toArray());

        $featuredServices = HomepageCache::rememberSection('services', $homePage->services_items_count ?? 6, 3600, function () use ($homePage) {
            return Service::where('status', true)->take($homePage->services_items_count ?? 6)->get();
        });

        $featuredProjects = HomepageCache::rememberSection('projects', $homePage->portfolio_items_count ?? 6, 3600, function () use ($homePage) {
            return PortfolioProject::with('images')
                ->where('is_published', true)
                ->where('is_featured', true)
                ->take($homePage->portfolio_items_count ?? 6)
                ->get();
        });

        $latestPublications = HomepageCache::rememberSection('publications', $homePage->blog_items_count ?? 3, 1800, function () use ($homePage) {
            return Publication::with('author')
                ->where('is_published', true)
                ->orderBy('published_at', 'desc')
                ->take($homePage->blog_items_count ?? 3)
                ->get();
        });

        $testimonials = HomepageCache::rememberSection('testimonials', $homePage->testimonials_items_count ?? 3, 3600, function () use ($homePage) {
            return Testimonial::where('is_featured', true)
                ->where('is_published', true)
                ->take($homePage->testimonials_items_count ?? 3)
                ->get();
        });

        $features = HomepageCache::rememberSection('features', $homePage->features_items_count ?? 4, 3600, function () use ($homePage) {
            return Feature::take($homePage->features_items_count ?? 4)->get();
        });

        return view('home', compact(
            'featuredServices',
            'featuredProjects',
            'latestPublications',
            'testimonials',
            'homePage',
            'features',
            'modules'
        ));
    }
}
