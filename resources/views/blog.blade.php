@extends('layouts.main')

@section('title', optional($page)->seo_title ?? 'Blog - ' . config('agency.seo.title_suffix'))
@section('description', optional($page)->seo_description ?? 'Latest articles and insights from ' . config('agency.seo.title_suffix'))

@section('content')
<main class="pt-24 pb-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-16">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4" style="letter-spacing: -0.02em;">
        {{ optional($page)->page_title ?? optional($page)->hero_title ?? 'Blog' }}
      </h1>
      <p class="text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto">
        {{ optional($page)->page_subtitle ?? optional($page)->hero_subtitle ?? 'Insights, tips, and latest news from the world of web development.' }}
      </p>
    </div>

    <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
      @foreach($publications as $publication)
      <article class="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300">
        {{-- Cover Image --}}
        <a href="{{ route('blog.show', $publication->slug) }}" class="block relative h-48 overflow-hidden bg-gray-100">
          @if($publication->cover_image)
            <img src="{{ asset('storage/'.$publication->cover_image) }}" alt="{{ $publication->title }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
          @else
            <div class="w-full h-full bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
              <svg class="w-12 h-12 text-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
              </svg>
            </div>
          @endif
        </a>
        <div class="p-6">
          @if($publication->categories->first())
            <span class="text-xs font-medium text-primary uppercase tracking-wide">
              {{ $publication->categories->first()->name }}
            </span>
          @endif
          <h2 class="text-lg font-semibold text-gray-900 mt-1 mb-2 group-hover:text-primary transition-colors">
            <a href="{{ route('blog.show', $publication->slug) }}">{{ $publication->title }}</a>
          </h2>
          <p class="text-gray-500 text-sm leading-relaxed mb-4 line-clamp-2">
            {{ $publication->excerpt ?? Str::limit(strip_tags($publication->content), 120) }}
          </p>
          <div class="flex items-center justify-between">
            <div class="text-xs text-gray-400">
              @if($publication->author)
                <span>{{ $publication->author->name }}</span>
              @endif
              @if($publication->published_at)
                <span class="ml-2">{{ $publication->published_at->format('M j, Y') }}</span>
              @endif
            </div>
            <a href="{{ route('blog.show', $publication->slug) }}"
               class="inline-flex items-center text-sm font-medium text-primary hover:text-primary/80 transition-colors">
              Read Publication
              <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </a>
          </div>
        </div>
      </article>
      @endforeach
    </div>

    @if($publications->isEmpty())
      <div class="rounded-xl border border-dashed border-gray-200 p-10 text-center">
        <h2 class="text-lg font-semibold text-gray-900">No publications available</h2>
        <p class="mt-2 text-gray-500">Published articles and updates will appear here.</p>
        <a href="{{ route('contact') }}" class="mt-4 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium h-10 px-6">
          Contact Us
        </a>
      </div>
    @endif

    @if($publications->hasPages())
    <div class="mt-12 flex justify-center">
      {{ $publications->links() }}
    </div>
    @endif
  </div>
</main>
@endsection
