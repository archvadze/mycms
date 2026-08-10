<?php

namespace App\Http\Controllers;

use App\Models\Guide;
use App\Models\Page;
use Illuminate\Http\Request;

class GuideController extends Controller
{
    public function index()
    {
        $page = \App\Models\Page::where('slug', 'guides')->first();
        $guides = Guide::with('category')
            ->whereNotNull('published_at')
            ->orderBy('created_at', 'desc')
            ->paginate($page->items_count ?? 12);

        return view('guides', compact('guides', 'page'));
    }

    public function show($slug)
    {
        $guide = Guide::with('category')
            ->whereNotNull('published_at')
            ->where('slug', $slug)
            ->firstOrFail();
        $page = \App\Models\Page::where('slug', 'guides')->first();

        return view('guides.show', compact('guide', 'page'));
    }
}
