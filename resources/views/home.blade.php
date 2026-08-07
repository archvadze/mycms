@extends('layouts.main')

@section('title', $homePage->seo_title ?? config('agency.name') . ' - ' . config('agency.tagline'))
@section('description', $homePage->seo_description ?? 'Professional web design and development services. Create stunning websites that drive results for your business.')

@section('content')

<!-- Hero Section -->
<section class="relative min-h-screen flex items-center justify-center overflow-hidden">
    <div class="absolute inset-0 z-0">
        @if($homePage && $homePage->hero_media_type === 'video' && $homePage->hero_video)
            <video
                autoplay
                muted
                loop
                playsinline
                class="w-full h-full object-cover">
                <source src="{{ asset('storage/' . $homePage->hero_video) }}" type="video/mp4">
            </video>
        @elseif($homePage && $homePage->hero_image)
            <img
                src="{{ asset('storage/' . $homePage->hero_image) }}"
                alt="{{ $homePage->hero_title ?? 'Hero image' }}"
                class="w-full h-full object-cover"
            />
        @endif
        <div class="absolute inset-0 bg-gradient-to-r from-gray-900/90 to-gray-900/70"></div>
    </div>

    <div class="relative z-10 max-w-7xl mx-auto px-4 text-center">
        <h1 class="text-4xl md:text-6xl font-bold text-white mb-6">
            {{ $homePage->hero_title ?? 'Build your digital presence' }}
        </h1>

        <p class="text-xl text-gray-200 mb-8 max-w-2xl mx-auto">
            {{ $homePage->hero_subtitle ?? 'Professional web design and development services that transform your vision into reality' }}
        </p>

        @if($homePage && $homePage->hero_button_text)
        <a href="{{ $homePage->hero_button_url ?? route('order.create') }}"
           class="inline-flex items-center px-8 py-4 text-lg font-medium text-white bg-primary rounded-lg hover:bg-primary/90">
            {{ $homePage->hero_button_text }}
        </a>
        @endif
    </div>
</section>



@php
$sectionOrder = $homePage->section_order ?? [];
$defaultOrder = ["portfolio" => 1, "services" => 2, "features" => 3, "testimonials" => 4, "blog" => 5];
$order = array_merge($defaultOrder, $sectionOrder);
asort($order);
@endphp

@foreach(array_keys($order) as $section)
    @if($section === "portfolio")
@if(($modules['module_portfolio'] ?? '1') !== '0' && ($homePage->show_portfolio ?? true))
<!-- Portfolio Section -->
<section class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">

        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">
                {{ $homePage->portfolio_title ?? 'Our Portfolio' }}
            </h2>

            <p class="text-xl text-gray-600">
                {{ $homePage->portfolio_subtitle ?? 'Showcasing our latest projects' }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">

            @foreach($featuredProjects ?? [] as $project)

            <div class="bg-white rounded-lg shadow-sm overflow-hidden hover:shadow-lg transition-all duration-300 group">

                @if($project->cover_image)
                <img
                    src="{{ asset('storage/'.$project->cover_image) }}"
                    class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300"
                >
                @else
                <div class="w-full h-48 bg-gray-200 flex items-center justify-center">
                    <span class="text-gray-500">No image available</span>
                </div>
                @endif

                <div class="p-6">

                    <h3 class="text-xl font-semibold text-gray-900 mb-2">
                        {{ $project->title }}
                    </h3>

                    <p class="text-gray-600 mb-4">
                        {{ Str::limit($project->description,100) }}
                    </p>

                    @if($project->technologies)

                    @php
                    $techs = is_array($project->technologies)
                        ? $project->technologies
                        : explode(',', $project->technologies);
                    @endphp

                    <div class="flex flex-wrap gap-2">

                        @foreach($techs as $tech)

                        @if(trim($tech))

                        <span class="px-2 py-1 text-xs bg-gray-100 rounded">
                            {{ trim($tech) }}
                        </span>

                        @endif
                        @endforeach

                    </div>
                    @if($project->project_url)
                    <a href="{{ $project->project_url }}" target="_blank" class="inline-flex items-center gap-1 mt-3 text-sm font-medium text-primary hover:text-primary/80 transition-colors">
                        View Project
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                    @endif

                    @endif

                </div>
            </div>

            @endforeach

        </div>

    </div>
</section>


@endif

    @elseif($section === "services")
@if(($modules['module_services'] ?? '1') !== '0' && ($homePage->show_services ?? true))
<!-- Services Section -->
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4" style="letter-spacing:-0.02em">
                {{ $homePage->services_title ?? 'Our Services' }}
            </h2>
            <p class="text-xl text-gray-600">
                {{ $homePage->services_subtitle ?? 'Comprehensive solutions to bring your digital vision to life' }}
            </p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            @foreach($featuredServices->take(4) as $service)
            @if($loop->index % 2 === 0)
            <div class="grid grid-cols-2 rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-8 flex flex-col justify-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-primary/10 text-primary mb-4">
                        <i class="fas fa-{{ $service->icon ?? 'code' }} text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $service->name }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">{{ Str::limit($service->description, 100) }}</p>
                    <a href="{{ route('services') }}" class="text-sm font-medium text-primary hover:text-primary/80 inline-flex items-center gap-1">
                        Learn more <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
                <div class="bg-gray-100 flex items-center justify-center min-h-48">
                    @if($service->image)
                        <img src="{{ asset('storage/'.$service->image) }}" alt="{{ $service->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="flex flex-col items-center justify-center text-gray-300 p-6">
                            <i class="fas fa-{{ $service->icon ?? 'code' }} text-5xl"></i>
                        </div>
                    @endif
                </div>
            </div>
            @else
            <div class="grid grid-cols-2 rounded-2xl overflow-hidden border border-gray-100 shadow-sm hover:shadow-md transition-shadow">
                <div class="bg-gray-100 flex items-center justify-center min-h-48">
                    @if($service->image)
                        <img src="{{ asset('storage/'.$service->image) }}" alt="{{ $service->name }}" class="w-full h-full object-cover">
                    @else
                        <div class="flex flex-col items-center justify-center text-gray-300 p-6">
                            <i class="fas fa-{{ $service->icon ?? 'code' }} text-5xl"></i>
                        </div>
                    @endif
                </div>
                <div class="p-8 flex flex-col justify-center">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-lg bg-primary/10 text-primary mb-4">
                        <i class="fas fa-{{ $service->icon ?? 'code' }} text-lg"></i>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-2">{{ $service->name }}</h3>
                    <p class="text-sm text-gray-600 leading-relaxed mb-4">{{ Str::limit($service->description, 100) }}</p>
                    <a href="{{ route('services') }}" class="text-sm font-medium text-primary hover:text-primary/80 inline-flex items-center gap-1">
                        Learn more <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </a>
                </div>
            </div>
            @endif
            @endforeach
        </div>


    </div>
</section>


@endif

    @elseif($section === "features")

@if($homePage->show_features ?? true)
<!-- Features Section -->
<section class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4">
                {{ $homePage->features_title ?? 'Why Choose Us' }}
            </h2>
            <p class="text-xl text-gray-600">
                {{ $homePage->features_subtitle ?? 'What sets us apart from the competition' }}
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            @foreach($features as $feature)
            <div class="text-center">
                <div class="w-16 h-16 bg-primary rounded-full flex items-center justify-center mx-auto mb-6">
                    <i class="fas fa-{{ $feature->icon ?? 'star' }} text-white text-2xl"></i>
                </div>
                <h3 class="text-lg font-semibold text-gray-900 mb-2">
                    {{ $feature->name }}
                </h3>
                <p class="text-gray-600 text-sm">
                    {{ $feature->description }}
                </p>
            </div>
            @endforeach
        </div>
    </div>
</section>
@endif
    @elseif($section === "testimonials")
@if(($modules['module_testimonials'] ?? '1') !== '0' && ($homePage->show_testimonials ?? true))
<!-- Testimonials -->
<section class="py-24 bg-white">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-3" style="letter-spacing:-0.02em">
                {{ $homePage->testimonials_title ?? 'What our clients say' }}
            </h2>
            <p class="text-gray-500">{{ $homePage->testimonials_subtitle ?? "Real feedback from businesses we've helped grow" }}</p>
        </div>

        <div class="grid md:grid-cols-2 gap-6">
            @foreach($testimonials ?? [] as $testimonial)
            <div class="border border-gray-200 rounded-2xl p-8">
                {{-- Quote icon SVG --}}
                <svg class="w-8 h-8 text-primary/30 mb-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M14.017 21v-7.391c0-5.704 3.731-9.57 8.983-10.609l.995 2.151c-2.432.917-3.995 3.598-3.995 5.849h4v10h-9.983zm-14.017 0v-7.391c0-5.704 3.748-9.57 9-10.609l.996 2.151c-2.433.917-3.996 3.598-3.996 5.849h3.983v10h-9.983z"/>
                </svg>

                <p class="text-gray-700 leading-relaxed mb-6">
                    {{ $testimonial->testimonial_text }}
                </p>

                {{-- Author --}}
                <div class="flex items-center gap-3">
                    @if($testimonial->photo)
                        <img src="{{ asset('storage/'.$testimonial->photo) }}"
                             alt="{{ $testimonial->client_name }}"
                             class="w-10 h-10 rounded-full object-cover">
                    @else
                        <div class="w-10 h-10 rounded-full bg-gray-200 flex items-center justify-center text-gray-500 font-bold text-sm">
                            {{ substr($testimonial->client_name, 0, 1) }}
                        </div>
                    @endif
                    <div>
                        <p class="font-semibold text-gray-900 text-sm">{{ $testimonial->client_name }}</p>
                        <p class="text-xs text-gray-500">
                            {{ $testimonial->client_position }}{{ $testimonial->company ? ', '.$testimonial->company : '' }}
                        </p>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

@endif

    @elseif($section === "blog")
@if(($modules['module_blog'] ?? '1') !== '0' && ($homePage->show_blog ?? false))
<!-- Blog Section -->
<section class="py-24 bg-gray-50">
    <div class="max-w-7xl mx-auto px-4">
        <div class="text-center mb-16">
            <h2 class="text-4xl font-bold text-gray-900 mb-4" style="letter-spacing:-0.02em">
                {{ $homePage->blog_title ?? 'Latest News' }}
            </h2>
            <p class="text-xl text-gray-600">
                {{ $homePage->blog_subtitle ?? 'Insights and updates from our team' }}
            </p>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            @foreach($latestPublications as $publication)
            <a href="{{ route('blog.show', $publication->slug) }}" class="bg-white rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow group">
                @if($publication->cover_image)
                <img src="{{ asset('storage/'.$publication->cover_image) }}" alt="{{ $publication->title }}" class="w-full h-48 object-cover group-hover:scale-105 transition-transform duration-300">
                @endif
                <div class="p-6">
                    <p class="text-xs text-gray-400 mb-2">{{ $publication->published_at?->format('M d, Y') }}</p>
                    <h3 class="text-lg font-semibold text-gray-900 mb-2 group-hover:text-primary transition-colors">
                        {{ $publication->title }}
                    </h3>
                    <p class="text-gray-500 text-sm line-clamp-2">{{ Str::limit($publication->excerpt ?? $publication->content, 100) }}</p>
                </div>
            </a>
            @endforeach
        </div>

    </div>
</section>
@endif

    @endif
@endforeach
@endsection
