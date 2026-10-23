@extends('layouts.auth', ['title' => 'Create your account'])
@section('auth-content')

@if ($errors->any())
    <div class="mb-4">
        <ul class="text-sm text-red-600 bg-red-50 border border-red-200 rounded-lg p-4">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<form method="POST" action="{{ route('register') }}" class="space-y-5">
    @csrf
    @if(request('redirect_to'))
        <input type="hidden" name="redirect_to" value="{{ request('redirect_to') }}">
    @endif

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
        <input type="text" name="name" required value="{{ old('name') }}"
               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
        <input type="email" name="email" required value="{{ old('email') }}"
               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Phone *</label>
        <input type="tel" name="phone" required value="{{ old('phone') }}"
               placeholder="+995 555 000 000"
               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Password *</label>
        <input type="password" name="password" required
               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
    </div>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Confirm Password *</label>
        <input type="password" name="password_confirmation" required
               class="appearance-none block w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-primary focus:border-primary sm:text-sm">
    </div>

    <button type="submit" onclick="this.disabled=true;this.form.submit()"
            class="w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-primary hover:bg-primary/90 transition-colors">
        Create account
    </button>

    <div class="text-center">
        <p class="text-sm text-gray-600">
            Already have an account?
            <a href="{{ route('login') }}" class="font-medium text-primary hover:text-primary/80">Sign in</a>
        </p>
    </div>
</form>

    {{-- Social Register --}}
    <div class="mt-6">
        <div class="relative">
            <div class="absolute inset-0 flex items-center">
                <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
                <span class="px-2 bg-white text-gray-500">Or sign up with</span>
            </div>
        </div>
        <div class="mt-4 grid grid-cols-1 max-w-xs mx-auto gap-3">
            <a href="{{ route('social.redirect', 'google') }}"
               class="flex justify-center items-center gap-2 px-3 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                </svg>
                Google
            </a>
        </div>
    </div>
@endsection
