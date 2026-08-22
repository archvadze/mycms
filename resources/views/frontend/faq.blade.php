@extends('layouts.main')
@section('title', 'FAQ - ' . config('agency.seo.title_suffix'))

@section('content')
<main class="pt-24 pb-20">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="text-center mb-12">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4" style="letter-spacing:-0.02em">
        Frequently Asked Questions
      </h1>
      <p class="text-xl text-gray-600">Find answers to common questions about our services.</p>
    </div>

    @foreach($categories as $category)
    @if($category->faqs->count() > 0)
    <div class="mb-10">
      <h2 class="text-lg font-bold text-gray-900 mb-4 pb-2 border-b border-gray-200">
        {{ $category->name }}
      </h2>
      <div class="space-y-3" x-data="{ open: null }">
        @foreach($category->faqs as $i => $faq)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
          <button @click="open = open === {{ $i }} ? null : {{ $i }}"
            class="w-full flex items-center justify-between px-6 py-4 text-left">
            <span class="font-medium text-gray-900">{{ $faq->question }}</span>
            <svg class="w-5 h-5 text-gray-400 transition-transform"
                 :class="open === {{ $i }} ? 'rotate-180' : ''"
                 fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
            </svg>
          </button>
          <div x-show="open === {{ $i }}" class="px-6 pb-4">
            <p class="text-gray-600 leading-relaxed">{{ $faq->answer }}</p>
          </div>
        </div>
        @endforeach
      </div>
    </div>
    @endif
    @endforeach

    <div class="mt-12 bg-primary/5 rounded-2xl p-8 text-center">
      <h3 class="text-lg font-bold text-gray-900 mb-2">Still have questions?</h3>
      <p class="text-gray-600 mb-4">Can't find what you're looking for? Contact our team.</p>
      <a href="{{ route('contact') }}"
         class="inline-flex items-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
        Contact Us
      </a>
    </div>

  </div>
</main>
@endsection
