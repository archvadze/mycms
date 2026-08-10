@extends('layouts.main')

@section('title', optional($page)->seo_title ?? 'Guides - ' . config('agency.seo.title_suffix'))
@section('description', optional($page)->seo_description ?? 'Comprehensive guides for web development')

@section('content')
<main class="pt-24 pb-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-16">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4" style="letter-spacing: -0.02em;">
        {{ optional($page)->page_title ?? optional($page)->hero_title ?? 'Guides' }}
      </h1>
      <p class="text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto">
        {{ optional($page)->page_subtitle ?? optional($page)->hero_subtitle ?? 'Step-by-step guides to help you master web development.' }}
      </p>
    </div>

    <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
      @foreach($guides as $guide)
      <article class="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300">

        {{-- Thumbnail --}}
        <a href="{{ route('guides.show', $guide->slug) }}" class="block relative h-48 overflow-hidden bg-gray-100">
          @if($guide->youtube_thumbnail)
            <img src="{{ $guide->youtube_thumbnail }}" alt="{{ $guide->title }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
            <div class="absolute inset-0 flex items-center justify-center">
              <div class="bg-red-600 text-white rounded-full w-12 h-12 flex items-center justify-center shadow-lg">
                <svg class="w-5 h-5 ml-1" fill="currentColor" viewBox="0 0 24 24">
                  <path d="M8 5v14l11-7z"/>
                </svg>
              </div>
            </div>
          @elseif($guide->cover_image)
            <img src="{{ asset('storage/'.$guide->cover_image) }}" alt="{{ $guide->title }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
          @else
            <div class="w-full h-full bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
              <svg class="w-12 h-12 text-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/>
              </svg>
            </div>
          @endif
        </a>

        <div class="p-6">
          @if($guide->category)
            <span class="text-xs font-medium text-primary uppercase tracking-wide">{{ $guide->category->name }}</span>
          @endif
          <h2 class="text-lg font-semibold text-gray-900 mt-1 mb-2 group-hover:text-primary transition-colors">
            <a href="{{ route('guides.show', $guide->slug) }}">{{ $guide->title }}</a>
          </h2>
          <p class="text-gray-500 text-sm leading-relaxed mb-4 line-clamp-2">
            {{ Str::limit(strip_tags($guide->content), 120) }}
          </p>
          <div class="flex items-center justify-between">
            @if($guide->published_at)
              <span class="text-xs text-gray-400">{{ $guide->published_at->format('M j, Y') }}</span>
            @endif
            <a href="{{ route('guides.show', $guide->slug) }}"
               class="inline-flex items-center text-sm font-medium text-primary hover:text-primary/80 transition-colors ml-auto">
              Read Guide
              <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
              </svg>
            </a>
          </div>
        </div>
      </article>
      @endforeach
    </div>

    @if($guides->isEmpty())
      <div class="rounded-xl border border-dashed border-gray-200 p-10 text-center">
        <h2 class="text-lg font-semibold text-gray-900">No guides published yet</h2>
        <p class="mt-2 text-gray-500">Published guides will appear here when new resources are available.</p>
        <a href="{{ route('services') }}" class="mt-4 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium h-10 px-6">
          View Services
        </a>
      </div>
    @endif

    @if($guides->hasPages())
    <div class="mt-12 flex justify-center">
      {{ $guides->links() }}
    </div>
    @endif
  </div>
</main>
@endsection
