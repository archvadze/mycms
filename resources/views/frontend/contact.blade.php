@extends('layouts.main')

@section('title', $page?->seo_title ?? 'Contact Us - ' . config('agency.seo.title_suffix'))
@section('description', $page?->seo_description ?? '')

@section('content')
@php
  $contactPhone = $page?->contact_phone ?: data_get($siteSettings, 'site_phone');
  $contactEmail = $page?->contact_email ?: data_get($siteSettings, 'site_email');
  $contactAddress = $page?->contact_address ?: data_get($siteSettings, 'contact_address');
  $emailParts = $contactEmail && str_contains($contactEmail, '@') ? explode('@', $contactEmail, 2) : null;
@endphp
<main class="pt-24 pb-20">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="text-center mb-12">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4" style="letter-spacing: -0.02em;">
        {{ $page?->page_title ?? $page?->title ?? 'Contact Us' }}
      </h1>
      <p class="text-xl text-gray-600 leading-relaxed">
        {{ $page?->page_subtitle ?? 'Send a message about a project, service, or support question.' }}
      </p>
    </div>

    {{-- Contact Info Cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">

      @if($contactPhone)
      <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex-shrink-0 w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Phone</p>
          <a href="tel:{{ $contactPhone }}" class="text-sm font-semibold text-gray-900 hover:text-primary transition-colors">
            {{ $contactPhone }}
          </a>
        </div>
      </div>
      @endif

      @if($emailParts)
      <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex-shrink-0 w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Email</p>
          <a href="#" class="obfuscated-email text-sm font-semibold text-gray-900 hover:text-primary transition-colors" data-user="{{ $emailParts[0] }}" data-domain="{{ $emailParts[1] }}">
            <span class="email-text">Email Us</span></a>
        </div>
      </div>
      @endif

      @if($contactAddress)
      <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex-shrink-0 w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Address</p>
          <p class="text-sm font-semibold text-gray-900">{{ $contactAddress }}</p>
        </div>
      </div>
      @endif

      @if($page?->working_hours)
      <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <div class="flex-shrink-0 w-10 h-10 bg-gray-900 rounded-lg flex items-center justify-center">
          <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
          </svg>
        </div>
        <div>
          <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">Working Hours</p>
          <p class="text-sm font-semibold text-gray-900">{{ $page->working_hours }}</p>
        </div>
      </div>
      @endif

    </div>

    {{-- Contact Form --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8">
      <h2 class="text-xl font-semibold text-gray-900 mb-2">Send us a message</h2>
      <p class="text-sm text-gray-500 mb-6">Share the context we need to route your message correctly.</p>

      @if(session('success'))
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 flex items-start gap-3">
          <svg class="h-5 w-5 text-green-500 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
          </svg>
          <p class="text-sm text-green-800">{{ session('success') }}</p>
        </div>
      @endif

      @if($errors->any())
        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
          <div class="flex">
            <svg class="h-5 w-5 text-red-400" viewBox="0 0 20 20" fill="currentColor">
              <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
            </svg>
            <div class="ml-3">
              <ul class="list-disc pl-5 space-y-1 text-sm text-red-700">
                @foreach($errors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          </div>
        </div>
      @endif

      <form action="{{ route('contact.send') }}" method="POST" class="space-y-6">
        @csrf
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label for="name" class="block text-sm font-medium text-gray-700">Full Name *</label>
            <input type="text" name="name" id="name" required value="{{ old('name') }}"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 text-gray-900 @error('name') border-red-400 @enderror">
            @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
          </div>
          <div class="space-y-2">
            <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
            <input type="email" name="email" id="email" required value="{{ old('email') }}"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 text-gray-900 @error('email') border-red-400 @enderror">
            @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="space-y-2">
          <label for="subject" class="block text-sm font-medium text-gray-700">Subject *</label>
          <input type="text" name="subject" id="subject" required value="{{ old('subject') }}"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 text-gray-900 @error('subject') border-red-400 @enderror">
          @error('subject')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <div class="space-y-2">
          <label for="message" class="block text-sm font-medium text-gray-700">Message *</label>
          <textarea name="message" id="message" rows="5" required
            class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 text-gray-900 @error('message') border-red-400 @enderror"
            placeholder="Tell us how we can help you...">{{ old('message') }}</textarea>
          @error('message')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>

        <button type="submit"
          class="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-4 py-2 w-full">
          Send Message
        </button>
      </form>
    </div>

    {{-- Google Maps --}}
    @if($page?->google_maps_embed)
    <div class="mt-6 rounded-xl overflow-hidden border border-gray-100 shadow-sm">
      {!! $page->google_maps_embed !!}
    </div>
    @endif

  </div>
</main>
@endsection
