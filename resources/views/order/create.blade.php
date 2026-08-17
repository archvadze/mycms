@extends('layouts.main')

@section('title', 'Start Your Project - ' . config('agency.seo.title_suffix'))

@section('content')
<main class="pt-24 pb-20">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="text-center mb-12">
      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4" style="letter-spacing: -0.02em;">
        Start your project
      </h1>
      <p class="text-xl text-gray-600 leading-relaxed">
        Fill out the form below and we'll get back to you shortly
      </p>
    </div>

    @guest
    {{-- Auth Section - მხოლოდ guest-ისთვის --}}
    <div x-data="{ tab: 'login', done: false }" x-show="!done">

      <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-2">Sign in to continue</h2>
        <p class="text-sm text-gray-500 mb-6">Sign in or create an account to place your order and track it in your dashboard.</p>

        {{-- Tabs --}}
        <div class="flex rounded-lg border border-gray-200 overflow-hidden mb-6">
          <button @click="tab = 'login'" type="button"
            :class="tab === 'login' ? 'bg-primary text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
            class="flex-1 text-sm font-medium py-2.5 transition-colors">
            Sign In
          </button>
          <button @click="tab = 'register'" type="button"
            :class="tab === 'register' ? 'bg-primary text-white' : 'bg-white text-gray-600 hover:bg-gray-50'"
            class="flex-1 text-sm font-medium py-2.5 transition-colors">
            Create Account
          </button>
        </div>

        {{-- Login --}}
        <div x-show="tab === 'login'">
          @if($errors->loginErrors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
              <ul class="text-sm text-red-700">
                @foreach($errors->loginErrors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          <form method="POST" action="{{ route('login') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ route('order.create') }}">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Email *</label>
              <input type="email" name="email" value="{{ old('email') }}" required
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Password *</label>
              <input type="password" name="password" required
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            </div>
            <div class="flex items-center justify-between">
              <label class="flex items-center gap-2 text-sm text-gray-600">
                <input type="checkbox" name="remember" class="rounded border-gray-300">
                Remember me
              </label>
              <a href="{{ route('password.request') }}" class="text-sm text-primary hover:text-primary/80">Forgot password?</a>
            </div>
            <button type="submit" onclick="this.disabled=true;this.form.submit()"
              class="w-full inline-flex justify-center items-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-4">
              Sign In & Continue
            </button>
          </form>
        </div>

        {{-- Register --}}
        <div x-show="tab === 'register'">
          @if($errors->registerErrors->any())
            <div class="bg-red-50 border border-red-200 rounded-lg p-3 mb-4">
              <ul class="text-sm text-red-700">
                @foreach($errors->registerErrors->all() as $error)
                  <li>{{ $error }}</li>
                @endforeach
              </ul>
            </div>
          @endif
          <form method="POST" action="{{ route('register') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="redirect_to" value="{{ route('order.create') }}">
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Full Name *</label>
              <input type="text" name="name" value="{{ old('name') }}" required
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            </div>
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Email *</label>
              <input type="email" name="email" value="{{ old('email') }}" required
                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            </div>
            <div class="grid grid-cols-2 gap-4">
              <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Password *</label>
                <input type="password" name="password" required
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
              </div>
              <div class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">Confirm *</label>
                <input type="password" name="password_confirmation" required
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
              </div>
            </div>
            <button type="submit" onclick="this.disabled=true;this.form.submit()"
              class="w-full inline-flex justify-center items-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-4">
              Create Account & Continue
            </button>
          </form>
        </div>
      </div>

      {{-- Skip option --}}
      <div class="text-center mb-8">
        <button @click="done = true" type="button"
          class="text-sm text-gray-400 hover:text-gray-600 underline underline-offset-2">
          Continue without signing in
        </button>
      </div>
    </div>

    {{-- Order form hidden until auth or skip --}}
    <div x-data="{ done: false }"
         x-show="done"
         x-cloak>
      @endguest

      {{-- Logged in banner --}}
      @auth
      <div class="bg-green-50 border border-green-100 rounded-xl p-4 mb-8 flex items-center gap-3">
        <svg class="w-5 h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-green-800">
          Signed in as <strong>{{ Auth::user()->name }}</strong> — your order will be linked to your account.
        </p>
      </div>
      @endauth

      {{-- Order Form --}}
      <form action="{{ route('order.store') }}" method="POST" class="space-y-6">
        @csrf
        @php
          $selectedServices = old('services', request('service') ? [(string) request('service')] : []);
        @endphp

        @if($errors->any())
          <div class="bg-red-50 border border-red-200 rounded-lg p-4">
            <ul class="list-disc pl-5 space-y-1 text-sm text-red-700">
              @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Full Name *</label>
            @auth
            <p class="h-10 w-full px-3 py-2 text-sm font-medium text-gray-900 bg-gray-50 border border-gray-200 rounded-md flex items-center">{{ auth()->user()->name }}</p>
            <input type="hidden" name="client_name" value="{{ auth()->user()->name }}">
            @else
            <input type="text" name="client_name" required value="{{ old('client_name') }}"
                   class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            @endauth
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Email *</label>
            @auth
            <p class="h-10 w-full px-3 py-2 text-sm font-medium text-gray-900 bg-gray-50 border border-gray-200 rounded-md flex items-center">{{ auth()->user()->email }}</p>
            <input type="hidden" name="email" value="{{ auth()->user()->email }}">
            @else
            <input type="email" name="email" required value="{{ old('email') }}"
                   class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            @endauth
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Phone</label>
            @auth
            <p class="h-10 w-full px-3 py-2 text-sm font-medium text-gray-900 bg-gray-50 border border-gray-200 rounded-md flex items-center">{{ auth()->user()->client?->phone ?? '-' }}</p>
            <input type="hidden" name="phone" value="{{ auth()->user()->client?->phone }}">
            @else
            <input type="tel" name="phone" value="{{ old('phone') }}"
                   class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            @endauth
          </div>
{{-- DOMAIN --}}
         <div class="space-y-2" x-data="{ 
    checking: false, 
    result: null, 
    popup: false,
    query: '{{ old("domain", request("domain", "")) }}' 
}">
    <label class="block text-sm font-medium text-gray-700">
        Domain Name <span class="text-gray-400 font-normal">(optional)</span>
    </label>
    <div class="flex gap-2">
        <input type="text" name="domain" placeholder="example.com (optional)"
               x-model="query"
               class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
        <button type="button"
            @click="
                checking = true; result = null; popup = false;
                fetch('/domain-search', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content},
                    body: JSON.stringify({domain: query})
                }).then(r => r.json()).then(d => { result = d; checking = false; popup = true; }).catch(() => { checking = false; })
            "
            :disabled="!query || checking"
            class="flex-shrink-0 h-10 px-3 text-xs font-medium rounded-md bg-gray-900 text-white hover:bg-gray-700 disabled:opacity-50 transition-colors whitespace-nowrap">
            <span x-show="!checking">Check</span>
            <span x-show="checking">...</span>
        </button>
    </div>

    {{-- Popup --}}
    <div x-show="popup && result && result.length"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center p-4"
         style="display:none;">

        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50" @click="popup = false"></div>

          {{-- Modal --}}
        <div class="relative bg-white rounded-xl shadow-2xl w-full max-w-md z-10 flex flex-col max-h-[90vh]">
            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h3 class="text-base font-semibold text-gray-900">Domain Availability</h3>
                <button @click="popup = false" type="button"
                        class="text-gray-400 hover:text-gray-600 text-xl leading-none">✕</button>
            </div>

            {{-- Results --}}
            <div class="p-6 space-y-2 overflow-y-auto flex-1">
                <template x-for="r in result" :key="r.domain">
                    <div class="flex items-center justify-between px-4 py-3 rounded-lg"
                         :class="r.available ? 'bg-green-50 border border-green-200' : 'bg-gray-50 border border-gray-200'">
                        <div class="flex items-center gap-3">
                            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0"
                                  :class="r.available ? 'bg-green-500' : 'bg-gray-400'"></span>
                            <div>
                                <p class="text-sm font-medium text-gray-900" x-text="r.domain"></p>
                                <p class="text-xs" :class="r.available ? 'text-green-600' : 'text-gray-500'"
                                   x-text="r.available ? 'Available' : 'Taken'"></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <button x-show="r.available" type="button"
                                @click="query = r.domain; document.querySelector('[name=domain]').value = r.domain; popup = false"
                                class="text-xs font-medium text-white bg-primary hover:bg-primary/90 px-3 py-1.5 rounded-md transition-colors">
                                Select
                            </button>
                            <a x-show="r.available" :href="'https://www.namecheap.com/domains/registration/results/?domain=' + r.domain"
                               target="_blank"
                               class="text-xs font-medium text-primary hover:underline">
                                Buy →
                            </a>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 border-t border-gray-100 text-center">
                <button @click="popup = false" type="button"
                        class="text-sm text-gray-500 hover:text-gray-700">Close</button>
            </div>
        </div>
    </div>
</div>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Website Type *</label>
          <select name="website_type" required
                  class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
            <option value="">Select website type</option>
            <option value="business" {{ old('website_type') == 'business' ? 'selected' : '' }}>Business Website</option>
            <option value="e-commerce" {{ old('website_type') == 'e-commerce' ? 'selected' : '' }}>E-commerce</option>
            <option value="portfolio" {{ old('website_type') == 'portfolio' ? 'selected' : '' }}>Portfolio</option>
            <option value="blog" {{ old('website_type') == 'blog' ? 'selected' : '' }}>Blog</option>
            <option value="landing-page" {{ old('website_type') == 'landing-page' ? 'selected' : '' }}>Landing Page</option>
            <option value="other" {{ old('website_type') == 'other' ? 'selected' : '' }}>Other</option>
          </select>
        </div>

        <div class="space-y-3">
          <label class="block text-sm font-medium text-gray-700">Services Required *</label>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach($services as $service)
            <label for="service-{{ $service->id }}"
              class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
              <input type="checkbox" name="services[]" value="{{ $service->id }}" id="service-{{ $service->id }}"
                     {{ in_array((string) $service->id, array_map('strval', $selectedServices), true) ? 'checked' : '' }}
                     class="h-4 w-4 text-primary border-gray-300 rounded">
              <span class="flex-1">
                <span class="text-sm font-medium text-gray-900">{{ $service->name }}</span>
                @if($service->base_price > 0)
                  <span class="text-xs text-gray-500 ml-1">(${{ number_format($service->base_price, 0) }})</span>
                @endif
              </span>
            </label>
            @endforeach
          </div>
        </div>

        <div class="space-y-3">
          <label class="block text-sm font-medium text-gray-700">Additional Features</label>
          <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
            @foreach($features as $feature)
            <label for="feature-{{ $feature->id }}"
              class="flex items-center gap-3 p-3 border border-gray-200 rounded-lg cursor-pointer hover:bg-gray-50 transition-colors">
              <input type="checkbox" name="features[]" value="{{ $feature->id }}" id="feature-{{ $feature->id }}"
                     {{ in_array($feature->id, old('features', [])) ? 'checked' : '' }}
                     class="h-4 w-4 text-primary border-gray-300 rounded">
              <span class="flex-1">
                <span class="text-sm font-medium text-gray-900">{{ $feature->name }}</span>
                @if($feature->price > 0)
                  <span class="text-xs text-gray-500 ml-1">(${{ number_format($feature->price, 0) }})</span>
                @endif
              </span>
            </label>
            @endforeach
          </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Preferred Timeline *</label>
            <select name="timeline" required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
              <option value="">Select timeline</option>
              <option value="asap" {{ old('timeline') == 'asap' ? 'selected' : '' }}>ASAP</option>
              <option value="1-month" {{ old('timeline') == '1-month' ? 'selected' : '' }}>1 Month</option>
              <option value="2-3-months" {{ old('timeline') == '2-3-months' ? 'selected' : '' }}>2-3 Months</option>
              <option value="3-6-months" {{ old('timeline') == '3-6-months' ? 'selected' : '' }}>3-6 Months</option>
              <option value="flexible" {{ old('timeline') == 'flexible' ? 'selected' : '' }}>Flexible</option>
            </select>
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Budget Range *</label>
            <select name="budget_range" required
                    class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">
              <option value="">Select budget</option>
              <option value="under-5k" {{ old('budget_range') == 'under-5k' ? 'selected' : '' }}>Under $5,000</option>
              <option value="5k-10k" {{ old('budget_range') == '5k-10k' ? 'selected' : '' }}>$5,000 - $10,000</option>
              <option value="10k-25k" {{ old('budget_range') == '10k-25k' ? 'selected' : '' }}>$10,000 - $25,000</option>
              <option value="25k-50k" {{ old('budget_range') == '25k-50k' ? 'selected' : '' }}>$25,000 - $50,000</option>
              <option value="over-50k" {{ old('budget_range') == 'over-50k' ? 'selected' : '' }}>Over $50,000</option>
            </select>
          </div>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Project Description *</label>
          <textarea name="project_description" rows="5" required
                    placeholder="Tell us about your project requirements..."
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">{{ old('project_description') }}</textarea>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Additional Requirements</label>
          <textarea name="additional_requirements" rows="3"
                    placeholder="Any specific technologies, integrations, or other requirements..."
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900">{{ old('additional_requirements') }}</textarea>
        </div>

        <button type="submit" onclick="this.disabled=true;this.form.submit()"
          class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-4 py-2 w-full">
          Submit Order Request
        </button>
      </form>

      @guest
    </div>
    @endguest

  </div>
</main>
@endsection
