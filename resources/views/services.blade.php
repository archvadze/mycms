@extends('layouts.main')
@section('title', optional($page)->seo_title ?? 'Services - ' . config('agency.name'))
@section('description', optional($page)->seo_description ?? 'Comprehensive web development services.')

@section('content')
<main class="pt-24 pb-20 bg-white">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="text-center mb-20">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4" style="letter-spacing:-0.02em;">
        {{ optional($page)->page_title ?? optional($page)->hero_title ?? 'Our Services' }}
      </h1>
      <p class="text-xl text-gray-600 max-w-2xl mx-auto leading-relaxed">
        {{ optional($page)->page_subtitle ?? optional($page)->hero_subtitle ?? 'Comprehensive solutions to bring your digital vision to life' }}
      </p>
      <a href="{{ route('order.create') }}"
         class="mt-6 inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
        Start a Project
      </a>
    </div>

    {{-- Services — Alternating Layout --}}
    <div class="space-y-32 pt-8">
      @foreach($services as $index => $service)
      @php
        $isLcpServiceImage = $loop->first && filled($service->image);
      @endphp

      @if($index % 2 === 0)
      <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <div class="flex flex-col justify-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-primary/10 text-primary mb-6">
            <i class="fas fa-{{ $service->icon ?? 'code' }} text-xl"></i>
          </div>
          <h2 class="text-3xl font-bold text-gray-900 mb-4" style="letter-spacing:-0.02em;">{{ $service->name }}</h2>
          <p class="text-gray-600 leading-relaxed mb-6 text-lg">{{ $service->description }}</p>
          @if($service->base_price)
            <p class="text-primary font-semibold mb-4">Starting at ${{ number_format($service->base_price, 0) }}</p>
          @endif
          <div>
            <a href="{{ auth()->check() && !auth()->user()->hasVerifiedEmail() ? route('verification.notice') : route('order.create') . '?service=' . $service->id }}"
               class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
              Start a Project
            </a>
          </div>
        </div>
        <div class="bg-gray-100 rounded-2xl aspect-video flex items-center justify-center overflow-hidden">
          @if($service->image)
            <img
              src="{{ asset('storage/'.$service->image) }}"
              alt="{{ $service->name }}"
              width="1200"
              height="675"
              loading="{{ $isLcpServiceImage ? 'eager' : 'lazy' }}"
              decoding="{{ $isLcpServiceImage ? 'auto' : 'async' }}"
              @if($isLcpServiceImage) fetchpriority="high" @endif
              class="w-full h-full object-cover rounded-2xl">
          @else
            <div class="flex flex-col items-center justify-center text-gray-300 p-8">
              <i class="fas fa-{{ $service->icon ?? 'code' }} text-6xl mb-3"></i>
              <span class="text-sm font-medium">{{ $service->name }}</span>
            </div>
          @endif
        </div>
      </div>

      @else
      <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
        <div class="bg-gray-100 rounded-2xl aspect-video flex items-center justify-center overflow-hidden">
          @if($service->image)
            <img
              src="{{ asset('storage/'.$service->image) }}"
              alt="{{ $service->name }}"
              width="1200"
              height="675"
              loading="{{ $isLcpServiceImage ? 'eager' : 'lazy' }}"
              decoding="{{ $isLcpServiceImage ? 'auto' : 'async' }}"
              @if($isLcpServiceImage) fetchpriority="high" @endif
              class="w-full h-full object-cover rounded-2xl">
          @else
            <div class="flex flex-col items-center justify-center text-gray-300 p-8">
              <i class="fas fa-{{ $service->icon ?? 'code' }} text-6xl mb-3"></i>
              <span class="text-sm font-medium">{{ $service->name }}</span>
            </div>
          @endif
        </div>
        <div class="flex flex-col justify-center">
          <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-primary/10 text-primary mb-6">
            <i class="fas fa-{{ $service->icon ?? 'code' }} text-xl"></i>
          </div>
          <h2 class="text-3xl font-bold text-gray-900 mb-4" style="letter-spacing:-0.02em;">{{ $service->name }}</h2>
          <p class="text-gray-600 leading-relaxed mb-6 text-lg">{{ $service->description }}</p>
          @if($service->base_price)
            <p class="text-primary font-semibold mb-4">Starting at ${{ number_format($service->base_price, 0) }}</p>
          @endif
          <div>
            <a href="{{ auth()->check() && !auth()->user()->hasVerifiedEmail() ? route('verification.notice') : route('order.create') . '?service=' . $service->id }}"
               class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
              Start a Project
            </a>
          </div>
        </div>
      </div>
      @endif

      @endforeach

      @if($services->isEmpty())
        <div class="text-center py-16 border border-dashed border-gray-200 rounded-xl">
          <h2 class="text-lg font-semibold text-gray-900">Services are being updated</h2>
          <p class="mt-2 text-gray-500">Please contact us if you want to discuss a project while the service list is unavailable.</p>
          <a href="{{ route('contact') }}" class="mt-4 inline-flex items-center justify-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium h-10 px-6">
            Contact Us
          </a>
        </div>
      @endif
    </div>

  </div>
</main>
@endsection
