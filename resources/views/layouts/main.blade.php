<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $defaultSeoDescription = 'Professional web design and development services';
        $configuredSeoDescription = data_get($siteSettings, 'seo_default_description');
        $seoDescription = filled($configuredSeoDescription) ? $configuredSeoDescription : $defaultSeoDescription;
    @endphp

    <title>{{ $title ?? config('app.name', 'Laravel') }}</title>
    <meta name="description" content="{{ $description ?? $seoDescription }}">
    <meta name="keywords" content="{{ $keywords ?? 'web development, web design, SEO, e-commerce, Georgia, Tbilisi' }}">
    <meta name="author" content="{{ data_get($siteSettings, 'site_name', 'Archvadze') }}">
    <meta name="robots" content="index, follow">

    {{-- Open Graph --}}
    <meta property="og:title" content="{{ $title ?? config('app.name') }}">
    <meta property="og:description" content="{{ $description ?? $seoDescription }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:site_name" content="{{ data_get($siteSettings, 'site_name', 'Archvadze') }}">
    @if(isset($ogImage))
    <meta property="og:image" content="{{ $ogImage }}">
    @endif

    {{-- Twitter Card --}}
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $title ?? config('app.name') }}">
    <meta name="twitter:description" content="{{ $description ?? $seoDescription }}">

    {{-- Canonical --}}
    <link rel="canonical" href="{{ url()->current() }}">


    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="font-sans antialiased bg-white">
   <!-- Header -->
    <header class="fixed top-0 left-0 right-0 z-50 bg-white/95 backdrop-blur-sm border-b border-gray-200">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <a href="{{ route('home') }}" class="text-2xl font-bold text-primary">
                    @if(data_get($siteSettings, 'site_logo'))
                        <img src="{{ asset('storage/' . data_get($siteSettings, 'site_logo')) }}"
                             alt="{{ data_get($siteSettings, 'site_name', 'archvadze') }}"
                             class="h-8 w-auto object-contain inline-block align-middle mr-1">
                    @endif
                    {{ data_get($siteSettings, 'site_name', 'archvadze') }}
                </a>

                <nav class="hidden md:flex items-center gap-8">
                    @foreach($headerMenuItems as $menuItem)
                    <a href="{{ $menuItem->url }}"
                       target="{{ $menuItem->open_in_new_tab ? '_blank' : '_self' }}"
                       class="text-sm font-medium transition-colors duration-200 {{ request()->is(ltrim($menuItem->url, '/') ?: '/') ? 'text-primary' : 'text-gray-600 hover:text-primary' }}">
                        {{ $menuItem->label }}
                    </a>
                    @endforeach
                </nav>

                <div class="hidden md:flex items-center gap-3">
                    @auth
                        @php
                            $canUseClientPortal = auth()->user()->hasRole('Client')
                                && auth()->user()->client()->exists();
                        @endphp

                        @if($canUseClientPortal)
                            <a href="{{ route('client-dashboard.index') }}" class="text-sm font-medium text-gray-600 hover:text-primary transition-colors">
                                {{ auth()->user()->name }}
                            </a>
                            <span class="text-gray-300">|</span>
                            <a href="{{ route('client-dashboard.profile') }}" class="text-sm font-medium text-gray-600 hover:text-primary transition-colors">
                                Profile
                            </a>
                        @elseif(auth()->user()->hasRole(['Super Admin', 'Admin', 'Editor', 'Support']))
                            <a href="/{{ config('agency.admin_path', 'manage') }}" class="text-sm font-medium text-gray-600 hover:text-primary transition-colors">
                                Admin Panel
                            </a>
                        @endif
                        <span class="text-gray-300">|</span>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="text-sm font-medium text-red-500 hover:text-red-700 transition-colors">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-gray-600 hover:text-primary transition-colors">
                            Login
                        </a>
                        <a href="{{ route('register') }}" class="text-sm font-medium text-white bg-primary hover:bg-primary/90 px-3 py-1.5 rounded-md transition-colors">
                            Register
                        </a>
                    @endauth
                </div>

                <button class="md:hidden p-2 text-gray-600 hover:text-primary transition-colors" onclick="toggleMobileMenu()">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>

        <div id="mobile-menu" class="md:hidden bg-white border-t border-gray-200 hidden">
            <nav class="px-4 py-4 space-y-3">
                @foreach($headerMenuItems as $menuItem)
                <a href="{{ $menuItem->url }}"
                   target="{{ $menuItem->open_in_new_tab ? '_blank' : '_self' }}"
                   class="block py-2 text-sm font-medium {{ request()->is(ltrim($menuItem->url, '/') ?: '/') ? 'text-primary' : 'text-gray-600' }}">
                    {{ $menuItem->label }}
                </a>
                @endforeach
                <div class="pt-3 border-t border-gray-200 space-y-2">
                    @auth
                        @php
                            $canUseClientPortal = auth()->user()->hasRole('Client')
                                && auth()->user()->client()->exists();
                        @endphp

                        @if($canUseClientPortal)
                            <a href="{{ route('client-dashboard.index') }}" class="block py-2 text-sm font-medium text-gray-600">
                                Dashboard
                            </a>
                        @elseif(auth()->user()->hasRole(['Super Admin', 'Admin', 'Editor', 'Support']))
                            <a href="/{{ config('agency.admin_path', 'manage') }}" class="block py-2 text-sm font-medium text-gray-600">
                                Admin Panel
                            </a>
                        @endif
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="block w-full text-left py-2 text-sm font-medium text-gray-600">
                                Logout
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="block py-2 text-sm font-medium text-gray-600">
                            Login
                        </a>
                    @endauth
                </div>
            </nav>
        </div>
    </header>

    <!-- Page Content -->
    <main class="pt-16">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-gray-950 text-gray-300 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-8">

                {{-- Brand + Newsletter --}}
                <div>
                    <span class="text-2xl font-bold text-white mb-3 block">
                        @if(data_get($siteSettings, 'site_logo'))
                            <img src="{{ asset('storage/' . data_get($siteSettings, 'site_logo')) }}"
                                 alt="{{ data_get($siteSettings, 'site_name', 'archvadze') }}"
                                 class="h-8 w-auto object-contain inline-block align-middle mr-1">
                        @endif
                        {{ data_get($siteSettings, 'site_name', 'archvadze') }}
                    </span>
                    <p class="text-sm leading-relaxed text-gray-400 mb-4">
                        {{ data_get($siteSettings, 'footer_tagline', 'Building exceptional digital experiences for businesses worldwide. Your vision, our expertise.') }}
                    </p>
                    {{-- Newsletter --}}
                    <form action="{{ route('newsletter.subscribe') }}" method="POST">
                        @csrf
                        @if(session('newsletter_success'))
                            <p class="text-green-400 text-xs">{{ session('newsletter_success') }}</p>
                        @else
                            <div class="flex gap-2">
                                <input type="email" name="email" required placeholder="your@email.com"
                                    class="flex-1 px-3 py-2 rounded-lg bg-gray-800 border border-gray-700 text-white text-xs placeholder-gray-500 focus:outline-none focus:border-primary">
                                <button type="submit"
                                    class="px-3 py-2 bg-primary text-white text-xs font-medium rounded-lg hover:bg-primary/90 transition-colors whitespace-nowrap">
                                    Subscribe
                                </button>
                            </div>
                        @endif
                    </form>
                </div>

                {{-- Quick Links --}}
                <div>
                    <span class="text-white font-semibold mb-4 block">Quick Links</span>
                    <nav class="space-y-2">
                        @foreach($footerMenuItems as $menuItem)
                        <a href="{{ $menuItem->url }}"
                           target="{{ $menuItem->open_in_new_tab ? '_blank' : '_self' }}"
                           class="block text-sm text-gray-400 hover:text-white transition-colors">
                            {{ $menuItem->label }}
                        </a>
                        @endforeach
                    </nav>
                </div>

                {{-- Contact --}}
                <div>
                    <span class="text-white font-semibold mb-4 block">Contact</span>
                    <div class="space-y-2">
                        <div class="flex items-center gap-2 text-sm text-gray-400">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 4.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            <a href="#" class="obfuscated-email hover:text-white transition-colors" data-user="{{ explode('@', data_get($siteSettings, 'site_email', 'info@archvadze.com'))[0] }}" data-domain="{{ explode('@', data_get($siteSettings, 'site_email', 'info@archvadze.com'))[1] }}">📧 Email Us</a>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-400">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                            </svg>
                            <span>{{ data_get($siteSettings, 'site_phone', '+995 555 123 456') }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-gray-400">
                            <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span>{{ data_get($siteSettings, 'contact_address', 'Tbilisi, Georgia') }}</span>
                        </div>
                    </div>
                    
                    {{-- Social Links --}}
                    <div class="flex gap-2 mt-4">
                        @if(data_get($siteSettings, 'social_facebook'))
                        <a href="{{ data_get($siteSettings, 'social_facebook') }}" target="_blank" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
                        @endif
                        @if(data_get($siteSettings, 'social_twitter'))
                        <a href="{{ data_get($siteSettings, 'social_twitter') }}" target="_blank" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.736-8.847L1.254 2.25H8.08l4.253 5.622 5.91-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                        @endif
                        @if(data_get($siteSettings, 'social_instagram'))
                        <a href="{{ data_get($siteSettings, 'social_instagram') }}" target="_blank" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
                        @endif
                        @if(data_get($siteSettings, 'social_linkedin'))
                        <a href="{{ data_get($siteSettings, 'social_linkedin') }}" target="_blank" class="w-8 h-8 bg-gray-800 rounded-lg flex items-center justify-center hover:bg-primary transition-colors"><svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/></svg></a>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Bottom Bar --}}
            <div class="pt-6 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-4">
                @php
                    $configuredCopyrightText = data_get($siteSettings, 'copyright_text');
                    $copyrightText = filled($configuredCopyrightText)
                        ? $configuredCopyrightText
                        : '© {year} ' . data_get($siteSettings, 'site_name', 'archvadze') . '. All rights reserved.';
                @endphp
                <p class="text-sm text-gray-500">{{ str_replace('{year}', date('Y'), $copyrightText) }}</p>
                <div class="flex gap-6">
                    @foreach($bottomMenuItems as $item)
                    <a href="{{ $item->url }}" class="text-sm text-gray-500 hover:text-white transition-colors">
                        {{ $item->label }}
                    </a>
                    @endforeach
                </div>
            </div>
        </div>
    </footer>

    <script>
        function toggleMobileMenu() {
            const menu = document.getElementById('mobile-menu');
            menu.classList.toggle('hidden');
        }
    </script>


    {{-- Head Scripts (Google Search Console, Bing, etc.) --}}
    @if(data_get($siteSettings, 'color_primary'))
    <style>
        :root {
            --primary: {{ data_get($siteSettings, 'color_primary') }};
            --ring: {{ data_get($siteSettings, 'color_primary') }};
        }
    </style>
    @endif
    @if(data_get($siteSettings, 'head_scripts'))
    {!! data_get($siteSettings, 'head_scripts') !!}
    @endif

    {{-- Schema.org JSON-LD --}}
    @php
        $schemaJson = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => data_get($siteSettings, 'site_name', 'Archvadze'),
            'url' => data_get($siteSettings, 'site_url', config('app.url')),
            'description' => 'Professional web design and development services',
            'contactPoint' => [
                '@type' => 'ContactPoint',
                'telephone' => data_get($siteSettings, 'site_phone', ''),
                'email' => data_get($siteSettings, 'site_email', ''),
                'contactType' => 'customer service',
            ],
            'address' => [
                '@type' => 'PostalAddress',
                'addressLocality' => 'Tbilisi',
                'addressCountry' => 'GE',
            ],
            'sameAs' => array_filter([
                data_get($siteSettings, 'social_facebook', ''),
                data_get($siteSettings, 'social_twitter', ''),
                data_get($siteSettings, 'social_instagram', ''),
                data_get($siteSettings, 'social_linkedin', ''),
            ]),
        ]);
    @endphp
    <script type="application/ld+json">{!! $schemaJson !!}</script>

    {{-- Google Analytics --}}
    @if(data_get($siteSettings, 'google_analytics'))
    {!! data_get($siteSettings, 'google_analytics') !!}
    @endif
@stack('scripts')
<script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".obfuscated-email").forEach(function(el) {
        var email = el.dataset.user + "@" + el.dataset.domain;
        el.href = "mailto:" + email;
        var span = el.querySelector(".email-text");
        if (span) span.textContent = email;
        else el.textContent = email;
    });
});
</script>
</body>
</html>
