@extends('layouts.main')
@section('title', 'Order Submitted - ' . config('agency.seo.title_suffix'))

@section('content')
<main class="pt-24 pb-20">
  <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Success Header --}}
    <div class="text-center mb-10">
      <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
        </svg>
      </div>
      <h1 class="text-4xl font-bold text-gray-900 mb-3" style="letter-spacing: -0.02em;">Order Submitted</h1>
      <p class="text-xl text-gray-600">Your request is saved and visible in your client dashboard.</p>
    </div>

    {{-- Order Details --}}
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
      <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Order #{{ $order->id }}</h2>
      </div>
      <dl class="divide-y divide-gray-100">
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
          <dt class="text-sm text-gray-500">Status</dt>
          <dd class="col-span-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ \App\Support\ClientPortalDisplay::orderStatusBadge($order->status) }}">
              {{ \App\Support\ClientPortalDisplay::orderStatusLabel($order->status) }}
            </span>
          </dd>
        </div>
        @if($order->payment_status)
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
          <dt class="text-sm text-gray-500">Payment</dt>
          <dd class="col-span-2">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium {{ \App\Support\ClientPortalDisplay::paymentStatusBadge($order->payment_status) }}">
              {{ \App\Support\ClientPortalDisplay::paymentStatusLabel($order->payment_status) }}
            </span>
          </dd>
        </div>
        @endif
        <div class="px-6 py-4 grid grid-cols-1 gap-1 sm:grid-cols-3 sm:gap-4">
          <dt class="text-sm text-gray-500">Domain</dt>
          <dd class="sm:col-span-2 text-sm text-gray-900 font-medium">{{ $order->domain ?: 'Not specified' }}</dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-1 gap-1 sm:grid-cols-3 sm:gap-4">
          <dt class="text-sm text-gray-500">Website Type</dt>
          <dd class="sm:col-span-2 text-sm text-gray-900">{{ Str::of($order->website_type)->replace('-', ' ')->title() }}</dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-1 gap-1 sm:grid-cols-3 sm:gap-4">
          <dt class="text-sm text-gray-500">Timeline</dt>
          <dd class="sm:col-span-2 text-sm text-gray-900">{{ Str::of($order->timeline ?? '-')->replace('-', ' ')->title() }}</dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-1 gap-1 sm:grid-cols-3 sm:gap-4">
          <dt class="text-sm text-gray-500">Budget</dt>
          <dd class="sm:col-span-2 text-sm text-gray-900">{{ Str::of($order->budget_range ?? '-')->replace('-', ' ')->title() }}</dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
          <dt class="text-sm text-gray-500">Services</dt>
          <dd class="col-span-2">
            @if($order->services->count() > 0)
              <div class="flex flex-wrap gap-1">
                @foreach($order->services as $service)
                  <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary">
                    {{ $service->name }}
                  </span>
                @endforeach
              </div>
            @else
              <span class="text-sm text-gray-400">None</span>
            @endif
          </dd>
        </div>
        @if($order->features->count() > 0)
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
          <dt class="text-sm text-gray-500">Features</dt>
          <dd class="col-span-2">
            <div class="flex flex-wrap gap-1">
              @foreach($order->features as $feature)
                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-700">
                  {{ $feature->name }}
                </span>
              @endforeach
            </div>
          </dd>
        </div>
        @endif
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
          <dt class="text-sm text-gray-500">Estimated Total</dt>
          <dd class="col-span-2 text-sm font-bold text-gray-900">${{ number_format($order->price_estimate, 2) }}</dd>
        </div>
        <div class="px-6 py-4 grid grid-cols-3 gap-4">
          <dt class="text-sm text-gray-500">Submitted</dt>
          <dd class="col-span-2 text-sm text-gray-900">{{ $order->created_at->format('M j, Y \a\t g:i A') }}</dd>
        </div>
      </dl>
    </div>

    {{-- Next Steps --}}
    <div class="bg-blue-50 rounded-xl border border-blue-100 p-6 mb-8">
      <h3 class="text-sm font-semibold text-blue-900 mb-3">What happens next</h3>
      <ol class="space-y-2 text-sm text-blue-800">
        <li class="flex items-start gap-2"><span class="font-bold">1.</span> We review your request and confirm any missing details.</li>
        <li class="flex items-start gap-2"><span class="font-bold">2.</span> You receive the final estimate and payment instructions when applicable.</li>
        <li class="flex items-start gap-2"><span class="font-bold">3.</span> Accepted work becomes a project in your dashboard.</li>
        <li class="flex items-start gap-2"><span class="font-bold">4.</span> Messages and files stay available from the project page.</li>
      </ol>
    </div>

    {{-- Payment Section --}}
    @if($order->payment_status === 'unpaid')
    <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-6 mb-6">
        <h3 class="text-base font-semibold text-yellow-900 mb-2">Complete Your Payment</h3>
        <p class="text-sm text-yellow-800 mb-4">
            Total: <strong>${{ number_format($order->price_estimate, 2) }}</strong>
        </p>
        @auth
        <a href="{{ route('payment.create', $order->id) }}"
           class="inline-flex items-center gap-2 rounded-md bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium h-10 px-6">
            <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                <path d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.474 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.312 2.86 1.312 4.521 0 .617-.076 1.28-.23 1.98-1.01 4.1-4.386 5.026-8.28 5.026h-2.1c-.495 0-.912.358-.99.848l-1.06 6.99c-.081.505-.513.874-1.024.874h.3z"/>
            </svg>
            Pay with PayPal
        </a>
        @else
        <a href="{{ route('login') }}"
           class="inline-flex items-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-6">
            Login to Pay
        </a>
        @endauth
    </div>
    @else
    <div class="bg-green-50 border border-green-200 rounded-xl p-4 mb-6 flex items-center gap-3">
        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-green-800 font-medium">Payment completed</p>
    </div>
    @endif

    {{-- Actions --}}
    <div class="flex flex-col sm:flex-row gap-4">
      <a href="{{ route('client-dashboard.index') }}"
         class="inline-flex justify-center items-center rounded-md text-sm font-medium bg-primary text-primary-foreground hover:bg-primary/90 h-10 px-6 py-2 flex-1">
        View Dashboard
      </a>
      <a href="{{ route('home') }}"
         class="inline-flex justify-center items-center rounded-md text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 h-10 px-6 py-2 flex-1">
        Back to Home
      </a>
    </div>

  </div>
</main>
@endsection
