@extends('layouts.main')
@section('title', $guide->title . ' - ' . config('agency.name') . ' Guides')
@section('description', Str::limit(strip_tags($guide->content), 160))
@section('og_type', 'article')
@if($guide->cover_image)
@section('og_image', asset('storage/'.$guide->cover_image))
@endif

@section('content')
<main class="pt-24 pb-20">
  <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <header class="mb-10 text-center">
      @if($guide->category)
        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary/10 text-primary mb-4">
          {{ $guide->category->name }}
        </span>
      @endif
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 leading-tight" style="letter-spacing: -0.02em;">
        {{ $guide->title }}
      </h1>
      @if($guide->published_at)
        <p class="text-sm text-gray-400">{{ $guide->published_at->format('M j, Y') }}</p>
      @endif
    </header>

    {{-- YouTube Video --}}
    @if($guide->youtube_embed)
    <div class="mb-10 rounded-xl overflow-hidden shadow-lg aspect-video">
      <iframe
        src="{{ $guide->youtube_embed }}"
        class="w-full h-full"
        frameborder="0"
        allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
        allowfullscreen>
      </iframe>
    </div>
    {{-- Cover Image (თუ YouTube არ არის) --}}
    @elseif($guide->cover_image)
    <div class="mb-10 rounded-xl overflow-hidden shadow-lg">
      <img src="{{ asset('storage/'.$guide->cover_image) }}" alt="{{ $guide->title }}"
           class="w-full h-64 object-cover">
    </div>
    @endif

    {{-- Content --}}
    <div class="prose prose-lg prose-gray max-w-none mb-12">
      {!! $guide->content !!}
    </div>

    {{-- Social Share --}}
    <div class="border-t border-gray-200 pt-8 mb-12">
      <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-4">Share</h3>
      <div class="flex flex-wrap gap-3" x-data="{ copied: false }">

        {{-- Facebook --}}
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#1877F2] text-white text-sm font-medium hover:bg-[#1877F2]/90 transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
          </svg>
          Facebook
        </a>

        {{-- X (Twitter) --}}
        <a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($guide->title) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black text-white text-sm font-medium hover:bg-black/80 transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.253 5.622 5.911-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
          </svg>
          X
        </a>

        {{-- LinkedIn --}}
        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url()->current()) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#0A66C2] text-white text-sm font-medium hover:bg-[#0A66C2]/90 transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
          </svg>
          LinkedIn
        </a>

        {{-- Copy Link --}}
        <button @click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors">
          <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <svg x-show="copied" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          <span x-text="copied ? 'Copied!' : 'Copy Link'"></span>
        </button>

      </div>
    </div>

    {{-- Back --}}
    <div class="text-center">
      <a href="{{ route('guides') }}"
         class="inline-flex items-center px-6 py-3 text-base font-medium rounded-md text-white bg-primary hover:bg-primary/90 transition-colors">
        <svg class="mr-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Guides
      </a>
    </div>

  </article>
</main>
@endsection
