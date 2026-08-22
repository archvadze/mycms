<?php
namespace App\Http\Controllers;

use App\Models\Publication;
use App\Models\Guide;
use App\Models\DigitalProduct;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $publications = Publication::where('is_published', true)
            ->orderBy('updated_at', 'desc')->get();
        $guides = Guide::whereNotNull('published_at')
            ->orderBy('updated_at', 'desc')->get();
        $products = DigitalProduct::where('is_published', true)
            ->orderBy('updated_at', 'desc')->get();
        $baseUrl = rtrim(config('app.url'), '/');

        $content = view('sitemap', compact('publications', 'guides', 'products', 'baseUrl'))->render();
        $content = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . ltrim($content);

        return response($content, 200)->header('Content-Type', 'text/xml');
    }
}
