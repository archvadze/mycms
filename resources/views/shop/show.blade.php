@extends('layouts.main')

@section('title', $product->name . ' - ' . config('agency.seo.title_suffix'))
@section('description', $product->short_description ?? $product->name)

@section('content')
<main class="pt-24 pb-20">

  {{-- Flash Messages --}}
  @if(session('success'))
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
    <div class="bg-green-50 border border-green-200 text-green-800 rounded-xl px-6 py-4 flex items-center gap-3">
      <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
      </svg>
      {{ session('success') }}
    </div>
  </div>
  @endif

  @if(session('error'))
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mb-6">
    <div class="bg-red-50 border border-red-200 text-red-800 rounded-xl px-6 py-4">
      {{ session('error') }}
    </div>
  </div>
  @endif

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">

      {{-- LEFT: Images --}}
      <div class="space-y-3">
        @php
          $allImages = collect();
          if ($product->image) $allImages->push(asset('storage/'.$product->image));
          if ($product->gallery_images) {
            foreach ($product->gallery_images as $img) {
              $allImages->push(asset('storage/'.$img));
            }
          }
        @endphp

        {{-- Main Image --}}
        <div class="relative w-full bg-gray-100 rounded-xl overflow-hidden" style="height: 380px;">
          <img id="main-image"
               src="{{ $allImages->first() ?? '' }}"
               alt="{{ $product->name }}"
               class="w-full h-full object-contain cursor-zoom-in"
               onclick="openLightbox(currentIndex)">

          @if($allImages->count() > 1)
          <button onclick="changeImage(-1)"
                  class="absolute left-3 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full w-9 h-9 flex items-center justify-center text-lg transition-colors">
            ‹
          </button>
          <button onclick="changeImage(1)"
                  class="absolute right-3 top-1/2 -translate-y-1/2 bg-black/40 hover:bg-black/60 text-white rounded-full w-9 h-9 flex items-center justify-center text-lg transition-colors">
            ›
          </button>
          <div class="absolute bottom-3 right-3 bg-black/40 text-white text-xs px-2 py-1 rounded-full">
            <span id="img-counter">1</span>/{{ $allImages->count() }}
          </div>
          @endif
        </div>

        {{-- Thumbnails --}}
        @if($allImages->count() > 1)
        <div class="flex gap-2 overflow-x-auto pb-1">
          @foreach($allImages as $i => $img)
          <div class="flex-shrink-0 w-20 h-16 rounded-lg overflow-hidden cursor-pointer border-2 transition-all
                      {{ $i === 0 ? 'border-primary' : 'border-transparent' }}"
               id="thumb-{{ $i }}"
               onclick="setImage({{ $i }})">
            <img src="{{ $img }}" alt="{{ $product->name }}"
                 class="w-full h-full object-cover hover:opacity-90 transition-opacity">
          </div>
          @endforeach
        </div>
        @endif

        {{-- Lightbox --}}
        <div id="lightbox"
             class="fixed inset-0 z-[9999] hidden items-center justify-center p-4"
             style="background: rgba(0,0,0,0); transition: background 0.3s ease;"
             onclick="closeLightbox()">
          <button onclick="closeLightbox()"
                  style="position:absolute; top:16px; right:16px; z-index:10000;"
                  class="bg-white/20 hover:bg-white/40 text-white rounded-full w-10 h-10 flex items-center justify-center text-xl transition-colors">
            ✕
          </button>
          @if($allImages->count() > 1)
          <button onclick="event.stopPropagation(); lightboxChange(-1)"
                  style="position:absolute; left:16px; top:50%; transform:translateY(-50%); z-index:10000;"
                  class="bg-white/20 hover:bg-white/40 text-white rounded-full w-12 h-12 flex items-center justify-center text-3xl transition-colors">
            ‹
          </button>
          <button onclick="event.stopPropagation(); lightboxChange(1)"
                  style="position:absolute; right:16px; top:50%; transform:translateY(-50%); z-index:10000;"
                  class="bg-white/20 hover:bg-white/40 text-white rounded-full w-12 h-12 flex items-center justify-center text-3xl transition-colors">
            ›
          </button>
          @endif
          <img id="lightbox-img" src="" alt="{{ $product->name }}"
               style="max-width:100%; max-height:85vh; object-fit:contain; border-radius:8px; opacity:0; transition: opacity 0.3s ease;"
               onclick="event.stopPropagation()">
          @if($allImages->count() > 1)
          <div style="position:absolute; bottom:16px; left:50%; transform:translateX(-50%); z-index:10000;"
               class="bg-black/50 text-white text-sm px-4 py-1 rounded-full">
            <span id="lightbox-counter">1</span>/{{ $allImages->count() }}
          </div>
          @endif
        </div>
      </div>

      {{-- RIGHT: Details --}}
      <div>
        <span class="text-xs font-medium text-primary uppercase tracking-wide">
          {{ ucwords(str_replace('-', ' ', $product->category)) }}
        </span>

        <h1 class="text-3xl font-bold text-gray-900 mt-2 mb-4">
          {{ $product->name }}
        </h1>

        @if($product->short_description)
        <p class="text-gray-600 leading-relaxed mb-6">
          {{ $product->short_description }}
        </p>
        @endif

        {{-- Price --}}
        <div class="mb-6">
          <span class="text-4xl font-bold text-gray-900">${{ number_format($product->price, 2) }}</span>
        </div>

        {{-- Version --}}
        @if($product->versions->count())
        <div class="mb-6 p-4 bg-gray-50 rounded-lg">
          <p class="text-sm text-gray-500">
            Latest version:
            <span class="font-medium text-gray-900">{{ $product->versions->first()->version_number }}</span>
          </p>
        </div>
        @endif

        {{-- Action Buttons --}}
        <div class="flex flex-wrap gap-3 mb-8">
          @if($hasPurchased)
            @php
              $activePurchase = \App\Models\Purchase::where('user_id', auth()->id())
                ->whereHas('version', fn($q) => $q->where('digital_product_id', $product->id))
                ->latest()->first();
            @endphp
            @if($activePurchase)
            <form method="POST" action="{{ route('purchase.download', $activePurchase) }}">
              @csrf
              <button type="submit"
                 class="inline-flex items-center gap-2 bg-green-600 text-white px-8 py-3 rounded-lg font-medium hover:bg-green-700 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Download
              </button>
            </form>
            @endif
          @else
            @auth
              <a href="{{ route('shop.checkout', $product->slug) }}"
                 class="inline-flex items-center gap-2 bg-primary text-white px-8 py-3 rounded-lg font-medium hover:bg-primary/90 transition-colors">
                Buy Now — ${{ number_format($product->price, 2) }}
              </a>
            @else
              <a href="{{ route('login') }}"
                 class="inline-flex items-center gap-2 bg-primary text-white px-8 py-3 rounded-lg font-medium hover:bg-primary/90 transition-colors">
                Login to Purchase
              </a>
            @endauth
          @endif

          @if($product->demo_url)
          <a href="{{ $product->demo_url }}" target="_blank"
             class="inline-flex items-center gap-2 border border-gray-300 text-gray-700 px-8 py-3 rounded-lg font-medium hover:border-primary hover:text-primary transition-colors">
            Live Demo
          </a>
          @endif
        </div>

        {{-- Social Share --}}
        <div class="pt-6 border-t border-gray-100">
          <p class="text-sm font-medium text-gray-500 mb-3">Share this product</p>
          <div class="flex flex-wrap items-center gap-2">
            <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"
               target="_blank" rel="noopener"
               class="flex items-center gap-2 px-3 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
              </svg>
              Facebook
            </a>
            <a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($product->name) }}"
               target="_blank" rel="noopener"
               class="flex items-center gap-2 px-3 py-2 bg-black hover:bg-gray-800 text-white text-sm font-medium rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.73-8.835L1.254 2.25H8.08l4.259 5.63zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
              </svg>
              X
            </a>
            <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url()->current()) }}"
               target="_blank" rel="noopener"
               style="background-color:#0077b5;" class="flex items-center gap-2 px-3 py-2 text-white text-sm font-medium rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
              </svg>
              LinkedIn
            </a>
            <button id="copy-btn"
                    onclick="navigator.clipboard.writeText('{{ url()->current() }}').then(() => { document.getElementById('copy-btn').textContent = 'Copied!'; setTimeout(() => { document.getElementById('copy-btn').innerHTML = '<svg class=\'w-4 h-4\' fill=\'none\' stroke=\'currentColor\' viewBox=\'0 0 24 24\'><path stroke-linecap=\'round\' stroke-linejoin=\'round\' stroke-width=\'2\' d=\'M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z\'></path></svg> Copy Link'; }, 2000) })"
                    class="flex items-center gap-2 px-3 py-2 border border-gray-300 hover:border-primary text-gray-700 hover:text-primary text-sm font-medium rounded-lg transition-colors">
              <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
              </svg>
              Copy Link
            </button>
          </div>
        </div>

        {{-- Tags --}}
        @if($product->tags && count($product->tags) > 0)
        <div class="flex flex-wrap gap-2 mt-6">
          @foreach($product->tags as $tag)
          <span class="px-3 py-1 bg-gray-100 text-gray-600 rounded-full text-xs">{{ $tag }}</span>
          @endforeach
        </div>
        @endif
      </div>
    </div>

    {{-- Description --}}
    @if($product->description)
    <div class="mt-16 border-t border-gray-100 pt-12">
      <h2 class="text-2xl font-bold text-gray-900 mb-6">Description</h2>
      <div class="prose max-w-none text-gray-600">
        {!! $product->description !!}
      </div>
    </div>
    @endif

  </div>
</main>

@push('scripts')
<script>
const images = @json($allImages->values());
let currentIndex = 0;

function setImage(index) {
  currentIndex = index;
  document.getElementById('main-image').src = images[index];
  document.getElementById('img-counter').textContent = index + 1;
  images.forEach((_, i) => {
    const thumb = document.getElementById('thumb-' + i);
    if (thumb) {
      thumb.className = thumb.className.replace('border-primary', 'border-transparent');
      if (i === index) thumb.className = thumb.className.replace('border-transparent', 'border-primary');
    }
  });
}

function changeImage(dir) {
  let next = currentIndex + dir;
  if (next < 0) next = images.length - 1;
  if (next >= images.length) next = 0;
  setImage(next);
}

function openLightbox(index) {
  currentIndex = index;
  const lb = document.getElementById('lightbox');
  const img = document.getElementById('lightbox-img');
  img.style.opacity = '0';
  img.src = images[index];
  lb.classList.remove('hidden');
  lb.classList.add('flex');
  document.body.style.overflow = 'hidden';
  requestAnimationFrame(() => {
    lb.style.background = 'rgba(0,0,0,0.9)';
    setTimeout(() => { img.style.opacity = '1'; }, 50);
  });
  if (document.getElementById('lightbox-counter')) {
    document.getElementById('lightbox-counter').textContent = index + 1;
  }
}

function closeLightbox() {
  const lb = document.getElementById('lightbox');
  const img = document.getElementById('lightbox-img');
  img.style.opacity = '0';
  lb.style.background = 'rgba(0,0,0,0)';
  setTimeout(() => {
    lb.classList.add('hidden');
    lb.classList.remove('flex');
    document.body.style.overflow = '';
  }, 300);
}

function lightboxChange(dir) {
  const img = document.getElementById('lightbox-img');
  let next = currentIndex + dir;
  if (next < 0) next = images.length - 1;
  if (next >= images.length) next = 0;
  currentIndex = next;
  img.style.opacity = '0';
  setTimeout(() => {
    img.src = images[next];
    img.style.opacity = '1';
  }, 200);
  if (document.getElementById('lightbox-counter')) {
    document.getElementById('lightbox-counter').textContent = next + 1;
  }
}

document.addEventListener('keydown', function(e) {
  const lb = document.getElementById('lightbox');
  if (!lb.classList.contains('hidden')) {
    if (e.key === 'Escape') closeLightbox();
    if (e.key === 'ArrowLeft') lightboxChange(-1);
    if (e.key === 'ArrowRight') lightboxChange(1);
  }
});
</script>
@endpush
@endsection
