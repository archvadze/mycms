@extends('layouts.main')
@section('title', 'Subscription Plans - ' . config('agency.seo.title_suffix'))
@section('content')
<main class="pt-24 pb-20 bg-gray-50 min-h-screen">
  <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
    
    <div class="text-center mb-12">
      <h1 class="text-4xl font-bold text-gray-900 mb-4">Support Plans</h1>
      <p class="text-gray-500">Choose ongoing support for active projects and maintenance.</p>
    </div>

    @if($current)
    <div class="mb-8 bg-blue-50 border border-blue-200 rounded-xl p-4 flex items-center gap-3">
      <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <p class="text-sm text-blue-800">
        You are currently on the <strong>{{ $current->plan->name }}</strong> plan
        ({{ \App\Support\ClientPortalDisplay::subscriptionStatusLabel($current->status) }}).
        @if($current->cancel_requested)
          Cancellation has already been requested.
        @endif
      </p>
    </div>
    @endif

    @if(session('success'))
    <div class="mb-6 bg-green-50 border border-green-200 rounded-xl p-4">
      <p class="text-sm text-green-800">{{ session('success') }}</p>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4">
      <p class="text-sm text-red-800">{{ session('error') }}</p>
    </div>
    @endif

    @if($plans->isEmpty())
      <div class="rounded-xl border border-dashed border-gray-200 bg-white p-8 text-center">
        <h2 class="text-sm font-semibold text-gray-900">No support plans are currently available</h2>
        <p class="mt-1 text-sm text-gray-500">Please check back later or contact the team through an active project.</p>
      </div>
    @else
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
      @foreach($plans as $plan)
      @php $isCurrentPlan = $current && $current->subscription_plan_id === $plan->id; @endphp
      <div class="bg-white rounded-2xl border {{ $plan->slug === 'pro' ? 'border-amber-400 shadow-lg' : 'border-gray-100' }} p-8 relative">
        
        @if($plan->slug === 'pro')
        <div class="absolute -top-3 left-1/2 -translate-x-1/2 bg-amber-400 text-white text-xs font-bold px-4 py-1 rounded-full">
          POPULAR
        </div>
        @endif

        <h2 class="text-xl font-bold text-gray-900 mb-2">{{ $plan->name }}</h2>
        <p class="text-gray-500 text-sm mb-6">{{ $plan->description }}</p>
        
        <div class="mb-6">
          <span class="text-4xl font-bold text-gray-900">€{{ number_format($plan->price, 0) }}</span>
          <span class="text-gray-400 text-sm"> / {{ $plan->billing_cycle }}</span>
        </div>

        @if($plan->features)
        <ul class="space-y-3 mb-8">
          @foreach($plan->features as $feature)
          <li class="flex items-center gap-2 text-sm text-gray-600">
            <svg class="w-4 h-4 text-green-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
            </svg>
            {{ $feature }}
          </li>
          @endforeach
        </ul>
        @endif

        @if($isCurrentPlan)
          <div class="w-full text-center py-3 bg-green-50 text-green-700 font-medium rounded-xl text-sm">
            Current Plan
          </div>
        @elseif($current)
          <div class="w-full text-center py-3 bg-gray-50 text-gray-400 font-medium rounded-xl text-sm">
            Already Subscribed
          </div>
        @else
          <form method="POST" action="{{ route('subscription.request', $plan) }}">
            @csrf
            <button type="submit"
                    onclick="return confirm('Subscribe to {{ $plan->name }} plan for €{{ $plan->price }}/{{ $plan->billing_cycle }}?')"
                    class="w-full py-3 {{ $plan->slug === 'pro' ? 'bg-amber-500 hover:bg-amber-600' : 'bg-gray-900 hover:bg-gray-700' }} text-white font-medium rounded-xl transition-colors text-sm">
              Get Started
            </button>
          </form>
        @endif
      </div>
      @endforeach
    </div>
    @endif

    <div class="mt-8 text-center">
      <a href="{{ route('client-dashboard.index') }}" class="text-sm text-gray-400 hover:text-gray-600">
        ← Back to Dashboard
      </a>
    </div>

  </div>
</main>
@endsection
