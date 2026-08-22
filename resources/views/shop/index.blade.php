@extends('layouts.main')

@section('title', optional($page)->seo_title ?? 'Shop - ' . config('agency.seo.title_suffix'))
@section('description', optional($page)->seo_description ?? optional($page)->page_subtitle ?? 'Digital products - themes, plugins, templates and more.')

@section('content')
<main class="pt-24 pb-20">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="text-center mb-16">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4" style="letter-spacing: -0.02em;">
        {{ $page?->page_title ?? "Digital Shop" }}
      </h1>
      <p class="text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto">
        {{ $page?->page_subtitle ?? "Premium digital products for your projects." }}
      </p>
    </div>

    {{-- Filters --}}
    <div class="flex flex-wrap gap-3 mb-10 items-center justify-between">
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('shop.index') }}"
           class="px-4 py-2 rounded-full text-sm font-medium border transition-colors
           {{ !request('category') ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary' }}">
          All
        </a>
        @foreach($categories as $cat)
        <a href="{{ route('shop.index', ['category' => $cat]) }}"
           class="px-4 py-2 rounded-full text-sm font-medium border transition-colors
           {{ request('category') === $cat ? 'bg-primary text-white border-primary' : 'bg-white text-gray-600 border-gray-200 hover:border-primary' }}">
          {{ ucwords(str_replace('-', ' ', $cat)) }}
        </a>
        @endforeach
      </div>

      {{-- Search --}}
      <form method="GET" action="{{ route('shop.index') }}" class="flex w-full gap-2 sm:w-auto">
        @if(request('category'))
          <input type="hidden" name="category" value="{{ request('category') }}">
        @endif
        <label for="shop-search" class="sr-only">Search products</label>
        <input id="shop-search" type="text" name="search" value="{{ request('search') }}"
               placeholder="Search..."
               class="min-w-0 flex-1 border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:border-primary">
        <button type="submit" class="bg-primary text-white px-4 py-2 rounded-lg text-sm">Search</button>
      </form>
    </div>

    {{-- Products Grid --}}
    @if($products->count())
    <div class="grid grid-cols-1 gap-8 md:grid-cols-2 lg:grid-cols-3">
      @foreach($products as $product)
      <div class="group bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden hover:shadow-lg transition-all duration-300">
        
        {{-- Image --}}
        <a href="{{ route('shop.show', $product->slug) }}" class="block relative h-48 overflow-hidden bg-gray-100">
          @if($product->image)
            <img src="{{ asset('storage/'.$product->image) }}" alt="{{ $product->name }}"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300">
          @else
            <div class="w-full h-full bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center">
              <svg class="w-12 h-12 text-primary/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
              </svg>
            </div>
          @endif
        </a>

        <div class="p-6">
          {{-- Category --}}
          <span class="text-xs font-medium text-primary uppercase tracking-wide">
            {{ ucwords(str_replace('-', ' ', $product->category)) }}
          </span>

          {{-- Name --}}
          <h2 class="text-lg font-semibold text-gray-900 mt-1 mb-2 group-hover:text-primary transition-colors">
            <a href="{{ route('shop.show', $product->slug) }}">{{ $product->name }}</a>
          </h2>

          {{-- Short Description --}}
          @if($product->short_description)
          <p class="text-gray-500 text-sm leading-relaxed mb-4 line-clamp-2">
            {{ $product->short_description }}
          </p>
          @endif

          {{-- Price & Button --}}
          <div class="flex items-center justify-between">
            <div>
              <span class="text-2xl font-bold text-gray-900">${{ number_format($product->price, 2) }}</span>
            </div>
            <a href="{{ route('shop.show', $product->slug) }}"
               class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">
              View Product
            </a>
          </div>
        </div>
      </div>
      @endforeach
    </div>

    {{-- Pagination --}}
    <div class="mt-12">
      {{ $products->links() }}
    </div>

    @else
    <div class="text-center py-20 border border-dashed border-gray-200 rounded-xl">
      <h2 class="text-lg font-semibold text-gray-900">No products found</h2>
      <p class="mt-2 text-gray-500">Try a different search or category. Published products will appear here when available.</p>
      <a href="{{ route('shop.index') }}" class="mt-4 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium h-10 px-6">
        Clear Filters
      </a>
    </div>
    @endif

  </div>
</main>
@endsection
