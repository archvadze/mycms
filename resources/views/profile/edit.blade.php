@extends('layouts.main')
@section('title', 'Edit Profile - ' . config('agency.seo.title_suffix'))

@section('content')
<style>
  .avatar-picker {
    display: flex;
    flex-wrap: wrap;
    gap: 16px;
    margin-bottom: 24px;
  }

  .avatar-picker__item {
    position: relative;
    width: 72px;
    height: 72px;
    aspect-ratio: 1 / 1;
    cursor: pointer;
  }

  .avatar-picker__choice {
    position: relative;
    width: 72px;
    height: 72px;
    aspect-ratio: 1 / 1;
    border-radius: 9999px;
    border: 3px solid transparent;
    background: #f9fafb;
    overflow: hidden;
    transition: border-color .2s ease, box-shadow .2s ease, background-color .2s ease;
  }

  .avatar-picker__choice img {
    width: 100%;
    height: 100%;
    aspect-ratio: 1 / 1;
    object-fit: cover;
    display: block;
  }

  .avatar-picker__check {
    position: absolute;
    right: -2px;
    bottom: -2px;
    display: none;
    width: 22px;
    height: 22px;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    border: 2px solid #fff;
    background: hsl(var(--primary));
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    line-height: 1;
  }

  .avatar-picker input:focus-visible + .avatar-picker__choice {
    box-shadow: 0 0 0 3px hsl(var(--primary) / .25);
  }

  .avatar-picker input:checked + .avatar-picker__choice {
    border-color: hsl(var(--primary));
    background: hsl(var(--primary) / .06);
    box-shadow: 0 0 0 3px hsl(var(--primary) / .14);
  }

  .avatar-picker input:checked + .avatar-picker__choice .avatar-picker__check {
    display: flex;
  }

  @media (min-width: 640px) {
    .avatar-picker {
      gap: 18px;
    }

    .avatar-picker__item,
    .avatar-picker__choice {
      width: 84px;
      height: 84px;
    }
  }
</style>

<main class="pt-24 pb-20 bg-gray-50 min-h-screen">
  <div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="mb-8">
      <h1 class="text-3xl font-bold text-gray-900" style="letter-spacing:-0.02em">Edit Profile</h1>
      <p class="mt-1 text-gray-500">Update your client-facing account and contact details.</p>
    </div>

    @if(session('status') === 'profile-updated')
      <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6 text-sm text-green-800">
        Profile updated successfully!
      </div>
    @endif

    {{-- Avatar Section --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 mb-6">
      @php
        $currentUser = Auth::user();
        $currentClient = $currentUser->client;
        $avatarOptions = collect(range(1, 12))->map(fn ($i) => "avatar{$i}.svg");
      @endphp

      <h2 class="text-lg font-semibold text-gray-900 mb-2">Choose an Avatar</h2>
      <p class="text-sm text-gray-500 mb-6">Select an avatar for your client portal profile.</p>

      <form method="POST" action="{{ route('profile.update') }}">
        @csrf
        @method('patch')
        <input type="hidden" name="name" value="{{ $currentUser->name }}">
        <input type="hidden" name="email" value="{{ $currentUser->email }}">
        <input type="hidden" name="phone" value="{{ $currentClient?->phone }}">
        <input type="hidden" name="country" value="{{ $currentClient?->country }}">
        <input type="hidden" name="company" value="{{ $currentClient?->company }}">
        <input type="hidden" name="website" value="{{ $currentClient?->website }}">
        <input type="hidden" name="social_linkedin" value="{{ $currentClient?->social_linkedin }}">
        <input type="hidden" name="social_facebook" value="{{ $currentClient?->social_facebook }}">
        <input type="hidden" name="birthday" value="{{ $currentClient?->birthday?->format('Y-m-d') }}">
        @foreach(($currentUser->tags ?? []) as $tag)
          <input type="hidden" name="tags[]" value="{{ $tag }}">
        @endforeach

        <div class="avatar-picker" aria-label="Choose an Avatar">
          @foreach($avatarOptions as $avatar)
          <label class="avatar-picker__item" for="avatar-{{ $loop->iteration }}">
            <input type="radio" name="avatar" value="{{ $avatar }}"
                   class="sr-only" id="avatar-{{ $loop->iteration }}"
                   {{ $currentUser->avatar === $avatar ? 'checked' : '' }}>
            <div class="avatar-picker__choice">
              <img src="{{ asset('avatars/' . $avatar) }}"
                   alt="Avatar {{ $loop->iteration }}">
              <span class="avatar-picker__check" aria-hidden="true">&#10003;</span>
            </div>
          </label>
          @endforeach
        </div>

        <button type="submit"
          class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
          Save Avatar
        </button>
      </form>
    </div>

    {{-- Profile Info --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 mb-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-2">Profile Information</h2>
      <p class="text-sm text-gray-500 mb-6">Only your personal and contact fields can be edited here.</p>

      <form method="POST" action="{{ route('profile.update') }}" class="space-y-5">
        @csrf
        @method('patch')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Name *</label>
            <input type="text" name="name" value="{{ old('name', Auth::user()->name) }}" required
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            @error('name')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Email *</label>
            <input type="email" name="email" value="{{ old('email', Auth::user()->email) }}" required
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
            @error('email')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Phone</label>
            <input type="tel" name="phone" value="{{ old('phone', Auth::user()->client?->phone) }}"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Country</label>
            <select name="country"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
              <option value="">— Select Country —</option>
              @foreach([
                'Georgia','United States','United Kingdom','Germany','France',
                'Turkey','Armenia','Azerbaijan','Russia','Ukraine','Poland',
                'Netherlands','Spain','Italy','Canada','Australia','UAE',
                'Israel','Sweden','Norway','Denmark','Finland','Switzerland',
                'Austria','Belgium','Portugal','Greece','Romania','Bulgaria',
                'Czech Republic','Hungary','Slovakia','Croatia','Serbia','Other'
              ] as $country)
                <option value="{{ $country }}" {{ old('country', Auth::user()->client?->country) === $country ? 'selected' : '' }}>
                  {{ $country }}
                </option>
              @endforeach
            </select>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Company</label>
            <input type="text" name="company" value="{{ old('company', Auth::user()->client?->company) }}"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Website</label>
            <input type="url" name="website" value="{{ old('website', Auth::user()->client?->website) }}"
                   placeholder="https://"
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">LinkedIn</label>
            <input type="url" name="social_linkedin" value="{{ old('social_linkedin', Auth::user()->client?->social_linkedin) }}"
                   placeholder="https://linkedin.com/in/..."
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          </div>
          <div class="space-y-2">
            <label class="block text-sm font-medium text-gray-700">Facebook</label>
            <input type="url" name="social_facebook" value="{{ old('social_facebook', Auth::user()->client?->social_facebook) }}"
                   placeholder="https://facebook.com/..."
              class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          </div>
        </div>

        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Birthday</label>
          <input type="date" name="birthday" value="{{ old('birthday', Auth::user()->client?->birthday?->format('Y-m-d')) }}"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        </div>

        {{-- Interests --}}
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Interests</label>
          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            @php
              $interests = ['Technology','Design','Business','Marketing','E-commerce',
                           'Education','Healthcare','Finance','Real Estate','Travel',
                           'Food & Restaurant','Fashion','Sports','Entertainment',
                           'Non-profit','Government'];
              $userTags = Auth::user()->tags ?? [];
            @endphp
            @foreach($interests as $interest)
            <label style="display:flex; align-items:center; gap:8px; cursor:pointer; padding:6px 10px;
                          border-radius:8px; border: 1px solid #e5e7eb;
                          background: {{ in_array($interest, $userTags) ? 'hsl(var(--primary)/0.1)' : 'white' }};"
                   id="label-{{ Str::slug($interest) }}">
              <input type="checkbox" name="tags[]" value="{{ $interest }}"
                     {{ in_array($interest, $userTags) ? 'checked' : '' }}
                     onchange="toggleInterest(this)"
                     style="accent-color: hsl(var(--primary));">
              <span style="font-size:13px; color:#374151;">{{ $interest }}</span>
            </label>
            @endforeach
          </div>
        </div>

        <button type="submit"
          class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
          Save Changes
        </button>
      </form>
    </div>

    {{-- Password Form --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 mb-6">
      <h2 class="text-lg font-semibold text-gray-900 mb-2">Change Password</h2>
      <p class="text-sm text-gray-500 mb-6">Use a strong password that you do not reuse elsewhere.</p>
      <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('put')
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Current Password</label>
          <input type="password" name="current_password" autocomplete="current-password"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          @error('current_password', 'updatePassword')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">New Password</label>
          <input type="password" name="password" autocomplete="new-password"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
          @error('password', 'updatePassword')<p class="text-red-500 text-xs mt-1">{{ $message }}</p>@enderror
        </div>
        <div class="space-y-2">
          <label class="block text-sm font-medium text-gray-700">Confirm Password</label>
          <input type="password" name="password_confirmation" autocomplete="new-password"
            class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring">
        </div>
        <button type="submit"
          class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
          Update Password
        </button>
      </form>
    </div>

  </div>
</main>

<script>
function toggleInterest(checkbox) {
    const label = checkbox.closest('label');
    label.style.background = checkbox.checked ? 'hsl(var(--primary)/0.1)' : 'white';
}
</script>
@endsection
