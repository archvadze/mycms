<?php

namespace App\Http\Controllers;

use App\Models\PortfolioProject;
use App\Models\Page;
use Illuminate\Http\Request;

class PortfolioController extends Controller
{
    public function index()
    {
        $page = \App\Models\Page::where('slug', 'portfolio')->first();
        $projects = PortfolioProject::with('images')
            ->where('is_published', true)
            ->orderBy('completed_at', 'desc')
            ->paginate($page->items_count ?? 9);

        return view('portfolio', compact('projects', 'page'));
    }

    public function show($id)
    {
        $project = PortfolioProject::with('images')->findOrFail($id);
        return view('portfolio.show', compact('project', 'page'));
    }
}
