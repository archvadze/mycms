@extends('layouts.main')

@section('title', 'My Dashboard - ' . config('agency.seo.title_suffix'))

@section('content')
<main class="pt-24 pb-20 bg-gray-50 min-h-screen">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

@if(!auth()->user()->hasVerifiedEmail())
<div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-center gap-3">
  <svg class="w-5 h-5 text-yellow-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <div class="flex-1">
    <p class="text-sm text-yellow-800 font-medium">Please verify your email address.</p>
    <p class="text-xs text-yellow-600 mt-0.5">Check your inbox for a verification link.</p>
  </div>
  <form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="text-xs font-medium text-yellow-700 hover:text-yellow-900 underline">Resend Email</button>
  </form>
</div>
@endif
    {{-- Header --}}
    <div class="mb-8 flex items-center justify-between">
      <div>
        <h1 class="text-3xl font-bold text-gray-900" style="letter-spacing:-0.02em">
          Welcome back, {{ Auth::user()->name }}!
        </h1>
        <p class="mt-1 text-gray-500">Here's an overview of your projects and recent activity.</p>
      </div>
      <a href="{{ route('order.create') }}"
         class="inline-flex items-center gap-2 rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-10 px-4">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
        </svg>
        New Order
      </a>
    </div>

    {{-- Stats --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
      <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Total Projects</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_projects'] }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Active</p>
        <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['active_projects'] }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Completed</p>
        <p class="text-3xl font-bold text-green-600 mt-1">{{ $stats['completed_projects'] }}</p>
      </div>
      <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
        <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Orders</p>
        <p class="text-3xl font-bold text-orange-500 mt-1">{{ $stats['total_orders'] }}</p>
      </div>
    </div>

    {{-- Main Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      {{-- Projects --}}
      <div class="lg:col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900">My Projects</h2>
        </div>
        <div class="p-6">
          @if($projects->count() > 0)
            <div class="space-y-3">
              @foreach($projects as $project)
              <div class="flex items-center justify-between p-4 border border-gray-100 rounded-lg hover:bg-gray-50 transition-colors">
                <div class="flex-1 min-w-0">
                  <h3 class="font-medium text-gray-900 truncate">{{ $project->title }}</h3>
                  <p class="text-sm text-gray-500 mt-0.5">{{ Str::limit($project->description, 80) }}</p>
                  <div class="flex items-center gap-2 mt-2">
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                      @if($project->status === 'completed') bg-green-100 text-green-800
                      @elseif($project->status === 'in_progress') bg-blue-100 text-blue-800
                      @elseif($project->status === 'review') bg-yellow-100 text-yellow-800
                      @else bg-gray-100 text-gray-800 @endif">
                      {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                    </span>
                    @if($project->deadline)
                      <span class="text-xs text-gray-400">Due: {{ \Carbon\Carbon::parse($project->deadline)->format('M j, Y') }}</span>
                    @endif
                  </div>
                  {{-- Progress bar --}}
                  @if($project->progress !== null)
                  <div class="mt-2">
                    <div class="flex items-center justify-between mb-1">
                      <span class="text-xs text-gray-400">Progress</span>
                      <span class="text-xs font-medium text-gray-700">{{ $project->progress }}%</span>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-1.5">
                      <div class="{{ $project->progressColor }} h-1.5 rounded-full transition-all duration-300"
                           style="width: {{ $project->progress }}%"></div>
                    </div>
                  </div>
                  @endif
                </div>
                <a href="{{ route('client-dashboard.project', $project->id) }}"
                   class="ml-4 text-primary hover:text-primary/80 flex-shrink-0">
                  <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                  </svg>
                </a>
              </div>
              @endforeach
            </div>
          @else
            <div class="text-center py-10">
              <svg class="mx-auto h-10 w-10 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <p class="mt-2 text-sm font-medium text-gray-900">No projects yet</p>
              <p class="text-sm text-gray-500">Place an order to get started.</p>
              <a href="{{ route('order.create') }}"
                 class="mt-4 inline-flex items-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-9 px-4">
                Place Order
              </a>
            </div>
          @endif
        </div>
      </div>

      {{-- Sidebar --}}
      <div class="space-y-6">

        {{-- Recent Orders --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Recent Orders</h2>
          </div>
          <div class="p-6">
            @if($orders->count() > 0)
              <div class="space-y-3">
               @foreach($orders as $order)
<div class="border border-gray-100 rounded-lg p-3">
    <div class="flex items-start justify-between gap-2">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-900 truncate">
                {{ $order->domain }}
            </p>

            <p class="text-xs text-gray-400 mt-0.5">
                {{ $order->created_at->format('M j, Y') }}
            </p>
        </div>

        <span class="flex-shrink-0 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
            @if($order->status === 'accepted') bg-green-100 text-green-800
            @elseif($order->status === 'contacted') bg-blue-100 text-blue-800
            @elseif($order->status === 'rejected') bg-red-100 text-red-800
            @else bg-yellow-100 text-yellow-800 @endif">
            {{ ucfirst($order->status) }}
        </span>
    </div>

    <div class="flex flex-wrap gap-2 mt-3">
        <a href="{{ route('order.success', $order->id) }}"
           class="text-xs font-medium text-primary hover:underline">
            View Order
        </a>

        @if($order->payment_status !== 'paid')
            <a href="{{ route('payment.create', $order->id) }}"
               class="text-xs font-medium text-orange-600 hover:underline">
                Pay with PayPal
            </a>
        @elseif($order->project)
            <a href="{{ route('client-dashboard.project', $order->project->id) }}"
               class="text-xs font-medium text-green-700 hover:underline">
                View Project
            </a>
                       @endif
                    </div>
                  </div>
                @endforeach
              </div>
            @else
              <p class="text-sm text-gray-400">No orders yet</p>
            @endif
          </div>
        </div>

        {{-- Recent Messages --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">Recent Messages</h2>
          </div>
          <div class="p-6">
            @if($recentMessages->count() > 0)
              <div class="space-y-3">
                @foreach($recentMessages as $message)
                <div class="border-l-2 border-primary pl-3">
                  <p class="text-sm text-gray-900">{{ Str::limit($message->message, 60) }}</p>
                  <p class="text-xs text-gray-400 mt-0.5">
                    {{ optional($message->project)->title }} • {{ $message->created_at->diffForHumans() }}
                  </p>
                </div>
                @endforeach
              </div>
            @else
              <p class="text-sm text-gray-400">No messages yet</p>
            @endif
          </div>
        </div>

        {{-- Profile Card --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
          <div class="px-6 py-4 border-b border-gray-100">
            <h2 class="text-base font-semibold text-gray-900">My Account</h2>
          </div>
          <div class="p-6 space-y-3">
            <div>
              <p class="text-xs text-gray-400 uppercase tracking-wide">Name</p>
              <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
            </div>
            <div>
              <p class="text-xs text-gray-400 uppercase tracking-wide">Email</p>
              <p class="text-sm text-gray-700">{{ Auth::user()->email }}</p>
            </div>
            <a href="{{ route('client-dashboard.profile') }}"
               class="w-full mt-2 inline-flex justify-center items-center rounded-md border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 text-sm font-medium h-9 px-4">
              Edit Profile
            </a>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit"
                class="w-full inline-flex justify-center items-center rounded-md border border-red-200 text-red-600 hover:bg-red-50 text-sm font-medium h-9 px-4">
                Logout
              </button>
            </form>
          </div>
        </div>

      </div>
</div>

    {{-- Subscription --}}
    @if(!$subscription)
    <div class="mt-6 bg-white rounded-xl border border-gray-100 shadow-sm p-6 flex items-center justify-between">
      <div>
        <h2 class="text-base font-semibold text-gray-900">Support Subscription</h2>
        <p class="text-sm text-gray-500 mt-1">Get dedicated support for your projects</p>
      </div>
      <a href="{{ route('subscription.plans') }}" class="inline-flex items-center gap-2 px-4 py-2 bg-amber-500 hover:bg-amber-600 text-white text-sm font-medium rounded-lg transition-colors">
        View Plans
      </a>
    </div>
    @endif
    @if(!auth()->user()->hasVerifiedEmail())
<div class="mb-6 bg-yellow-50 border border-yellow-200 rounded-xl p-4 flex items-center gap-3">
  <svg class="w-5 h-5 text-yellow-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
  <div class="flex-1">
    <p class="text-sm text-yellow-800 font-medium">Please verify your email address.</p>
    <p class="text-xs text-yellow-600 mt-0.5">Check your inbox for a verification link.</p>
  </div>
  <form method="POST" action="{{ route('verification.send') }}">
    @csrf
    <button type="submit" class="text-xs font-medium text-yellow-700 hover:text-yellow-900 underline">Resend Email</button>
  </form>
</div>
@endif

@if($subscription)
    <div class="mt-6 bg-white rounded-xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">My Subscription</h2>
        <span class="text-xs px-2 py-1 rounded-full font-medium {{ $subscription->status === 'active' ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }}">
          {{ ucfirst($subscription->status) }}
        </span>
      </div>
      <div class="p-6 flex items-center justify-between gap-4">
        <div>
          <p class="font-semibold text-gray-900">{{ $subscription->plan->name }}</p>
          <p class="text-sm text-gray-500">€{{ $subscription->plan->price }} {{ $subscription->plan->billing_label }}</p>
          @if($subscription->next_invoice_at)
          <p class="text-xs text-gray-400 mt-1">Next invoice: {{ $subscription->next_invoice_at->format('M d, Y') }}</p>
          @endif
        </div>
        <div>
          @if($subscription->cancel_requested)
            <span class="text-sm text-orange-500 font-medium">Cancellation pending...</span>
          @elseif($subscription->status === 'active')
            <form method="POST" action="{{ route('subscription.cancel') }}">
              @csrf
              <button type="submit" onclick="return confirm('Cancel subscription?')" class="text-sm text-red-500 hover:text-red-700 font-medium">
                Cancel Subscription
              </button>
            </form>
          @endif
        </div>
      </div>
    </div>
    @endif

        {{-- Digital Purchases --}}
    @if(isset($purchases) && $purchases->count())
    <div class="mt-6 bg-white rounded-xl border border-gray-100 shadow-sm">
      <div class="px-6 py-4 border-b border-gray-100 flex items-center justify-between">
        <h2 class="text-base font-semibold text-gray-900">My Digital Purchases</h2>
        <span class="text-xs text-gray-400">{{ $purchases->count() }} product(s)</span>
      </div>
      <div class="divide-y divide-gray-100">
        @foreach($purchases as $purchase)
        <div class="p-6 flex items-center justify-between gap-4">
          <div class="flex items-center gap-4">
            @if($purchase->version->product->image)
             <a href="{{ route('shop.show', $purchase->version->product->slug) }}">
 <img src="{{ asset('storage/'.$purchase->version->product->image) }}"
                   class="w-12 h-12 rounded-lg object-cover flex-shrink-0">
</a>
            @else
              <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                </svg>
              </div>
            @endif
            <div>
              <a href="{{ route('shop.show', $purchase->version->product->slug) }}" 
   class="font-medium text-gray-900 hover:text-primary transition-colors">
  {{ $purchase->version->product->name }}
</a>
              <p class="text-sm text-gray-500">
                v{{ $purchase->version->version_number }} •
                ${{ number_format($purchase->amount, 2) }} •
                {{ $purchase->created_at->format('M d, Y') }}
              </p>
              <p class="text-xs text-gray-400">
                {{ $purchase->download_limit }} downloads remaining
                @if($purchase->download_expires_at)
                  • expires {{ $purchase->download_expires_at->format('M d, Y') }}
                @endif
              </p>
              @if($purchase->license_key)
              <p class="text-xs font-mono text-gray-500 mt-1">
                🔑 {{ $purchase->license_key }}
              </p>
              @endif

 </div>
          </div>
          @if($purchase->download_limit > 0)
          <a href="{{ route('purchase.download', $purchase) }}"
             class="flex-shrink-0 inline-flex items-center gap-2 bg-primary text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-primary/90 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            Download
          </a>
          @else
          <span class="text-sm text-gray-400 flex-shrink-0">Limit reached</span>
          @endif
        </div>
        @endforeach
      </div>
    </div>
    @endif

  </div>
</main>
@endsection
