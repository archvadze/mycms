<?php
namespace App\Http\Controllers;
use App\Models\Publication;
use App\Models\Page;
use Illuminate\Http\Request;

class PublicationController extends Controller
{
    public function index()
    {
        $page = \App\Models\Page::where('slug', 'blog')->first();
        $publications = Publication::with(['author', 'categories'])
            ->where('is_published', true)
            ->orderBy('published_at', 'desc')
            ->paginate($page->items_count ?? 10);

        return view('blog', compact('publications', 'page'));
    }

    public function show($slug)
    {
        $publication = Publication::with([
                'author',
                'categories',
                'tags',
                'approvedComments.user',
                'approvedComments.replies' => fn($q) => $q->where('is_approved', true)->with(['user', 'replyToUser']),
            ])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();
        $page = \App\Models\Page::where('slug', 'blog')->first();
        return view('blog.show', compact('publication', 'page'));
    }
}
