@extends('layouts.main')

@section('title', 'My Dashboard - ' . config('agency.seo.title_suffix'))

@section('content')
<main class="pt-24 pb-20 bg-gray-50 min-h-screen">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="mb-8 flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
      <div>
        <p class="text-sm font-medium text-primary">Client Portal</p>
        <h1 class="mt-1 text-3xl font-bold text-gray-900">Welcome back, {{ Auth::user()->name }}</h1>
        <p class="mt-2 max-w-2xl text-sm text-gray-500">
          Track active work, recent messages, orders, downloads, and support subscription status.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <a href="{{ route('order.create') }}"
           class="inline-flex h-10 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">
          Start a Project
        </a>
        <a href="{{ route('shop.index') }}"
           class="inline-flex h-10 items-center justify-center rounded-md border border-gray-300 bg-white px-4 text-sm font-medium text-gray-700 hover:bg-gray-50">
          Browse Shop
        </a>
      </div>
    </div>

    @if(session('success'))
      <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
        {{ session('success') }}
      </div>
    @endif

    @if(session('error'))
      <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
        {{ session('error') }}
      </div>
    @endif

    <section aria-label="Account summary" class="mb-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Active Projects</p>
        <p class="mt-1 text-3xl font-bold text-blue-600">{{ $stats['active_projects'] }}</p>
      </div>
      <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Pending Orders</p>
        <p class="mt-1 text-3xl font-bold text-yellow-600">{{ $stats['pending_orders'] }}</p>
      </div>
      <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Completed</p>
        <p class="mt-1 text-3xl font-bold text-green-600">{{ $stats['completed_projects'] }}</p>
      </div>
      <div class="rounded-xl border border-gray-100 bg-white p-5 shadow-sm">
        <p class="text-xs font-medium uppercase tracking-wide text-gray-500">Total Orders</p>
        <p class="mt-1 text-3xl font-bold text-gray-900">{{ $stats['total_orders'] }}</p>
      </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <section class="lg:col-span-2 rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <div>
            <h2 class="text-lg font-semibold text-gray-900">Recent Projects</h2>
            <p class="mt-1 text-sm text-gray-500">Your latest owned projects across all statuses and their most recent visible activity.</p>
          </div>
        </div>

        <div class="p-6">
          @forelse($projects as $project)
            <article class="mb-3 rounded-lg border border-gray-100 p-4 last:mb-0">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <h3 class="truncate text-base font-semibold text-gray-900">{{ $project->title }}</h3>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Support\ClientPortalDisplay::projectStatusBadge($project->status) }}">
                      {{ \App\Support\ClientPortalDisplay::projectStatusLabel($project->status) }}
                    </span>
                  </div>

                  @if($project->description)
                    <p class="mt-1 text-sm text-gray-500">{{ Str::limit($project->description, 100) }}</p>
                  @endif

                  <div class="mt-3 grid gap-3 text-xs text-gray-500 sm:grid-cols-2">
                    <div>
                      <span class="font-medium text-gray-700">Progress:</span>
                      {{ $project->progress ?? 0 }}%
                    </div>
                    @if($project->deadline)
                      <div>
                        <span class="font-medium text-gray-700">Deadline:</span>
                        {{ $project->deadline->format('M j, Y') }}
                      </div>
                    @endif
                  </div>

                  <div class="mt-3 h-1.5 w-full rounded-full bg-gray-100">
                    <div class="{{ $project->progressColor }} h-1.5 rounded-full" style="width: {{ min(100, max(0, $project->progress ?? 0)) }}%"></div>
                  </div>

                  @if($project->latestMessage)
                    <p class="mt-3 text-xs text-gray-500">
                      Latest message from {{ $project->latestMessage->sender?->name ?? 'Team' }}:
                      <span class="text-gray-700">{{ Str::limit($project->latestMessage->message, 90) }}</span>
                    </p>
                  @else
                    <p class="mt-3 text-xs text-gray-400">No messages on this project yet.</p>
                  @endif
                </div>

                <a href="{{ route('client-dashboard.project', $project->id) }}"
                   class="inline-flex h-9 items-center justify-center rounded-md border border-gray-300 bg-white px-3 text-sm font-medium text-gray-700 hover:bg-gray-50">
                  View Project
                </a>
              </div>
            </article>
          @empty
            <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center">
              <h3 class="text-sm font-semibold text-gray-900">No projects yet</h3>
              <p class="mt-1 text-sm text-gray-500">Start a project request and accepted work will appear here.</p>
              <a href="{{ route('order.create') }}"
                 class="mt-4 inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                Start a Project
              </a>
            </div>
          @endforelse
        </div>
      </section>

      <aside class="space-y-6">
        <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
          <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Recent Messages</h2>
          </div>
          <div class="p-6">
            @forelse($recentMessages as $message)
              <a href="{{ route('client-dashboard.project', $message->project_id) }}"
                 class="mb-3 block rounded-lg border border-gray-100 p-3 hover:bg-gray-50 last:mb-0">
                <p class="text-sm text-gray-900">{{ Str::limit($message->message, 80) }}</p>
                <p class="mt-1 text-xs text-gray-500">
                  {{ optional($message->project)->title }} · {{ $message->created_at->diffForHumans() }}
                </p>
              </a>
            @empty
              <div class="rounded-lg border border-dashed border-gray-200 p-5 text-sm text-gray-500">
                No recent team messages. Project updates will appear here when the team replies.
              </div>
            @endforelse
          </div>
        </section>

        <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
          <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Quick Actions</h2>
          </div>
          <div class="grid gap-2 p-6">
            <a href="{{ route('client-dashboard.profile') }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Edit Profile</a>
            <a href="{{ route('subscription.plans') }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Support Plans</a>
            <a href="{{ route('shop.index') }}" class="rounded-md border border-gray-200 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">Digital Products</a>
          </div>
        </section>

        <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
          <div class="border-b border-gray-100 px-6 py-4">
            <h2 class="text-base font-semibold text-gray-900">Account</h2>
          </div>
          <div class="space-y-3 p-6">
            <div>
              <p class="text-xs uppercase tracking-wide text-gray-400">Name</p>
              <p class="text-sm font-medium text-gray-900">{{ Auth::user()->name }}</p>
            </div>
            <div>
              <p class="text-xs uppercase tracking-wide text-gray-400">Email</p>
              <p class="break-all text-sm text-gray-700">{{ Auth::user()->email }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}">
              @csrf
              <button type="submit" class="w-full rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                Logout
              </button>
            </form>
          </div>
        </section>
      </aside>
    </div>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
      <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <h2 class="text-base font-semibold text-gray-900">Recent Orders</h2>
          <a href="{{ route('order.create') }}" class="text-sm font-medium text-primary hover:text-primary/80">New Order</a>
        </div>
        <div class="divide-y divide-gray-100">
          @forelse($orders as $order)
            <article class="p-6">
              <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0">
                  <p class="truncate text-sm font-semibold text-gray-900">
                    {{ $order->domain ?: \App\Support\ClientPortalDisplay::orderStatusLabel($order->website_type) . ' request' }}
                  </p>
                  <p class="mt-1 text-xs text-gray-500">
                    Submitted {{ $order->created_at->format('M j, Y') }}
                    @if($order->price_estimate)
                      · Estimate ${{ number_format($order->price_estimate, 2) }}
                    @endif
                  </p>
                  <div class="mt-2 flex flex-wrap gap-2">
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Support\ClientPortalDisplay::orderStatusBadge($order->status) }}">
                      {{ \App\Support\ClientPortalDisplay::orderStatusLabel($order->status) }}
                    </span>
                    <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Support\ClientPortalDisplay::paymentStatusBadge($order->payment_status) }}">
                      {{ \App\Support\ClientPortalDisplay::paymentStatusLabel($order->payment_status) }}
                    </span>
                  </div>
                </div>
                <div class="flex flex-wrap gap-2 sm:justify-end">
                  <a href="{{ route('order.success', $order->id) }}" class="text-sm font-medium text-primary hover:underline">View Order</a>
                  @if($order->payment_status !== 'paid')
                    <a href="{{ route('payment.create', $order->id) }}" class="text-sm font-medium text-yellow-700 hover:underline">Pay</a>
                  @elseif($order->project)
                    <a href="{{ route('client-dashboard.project', $order->project->id) }}" class="text-sm font-medium text-green-700 hover:underline">View Project</a>
                  @endif
                </div>
              </div>
            </article>
          @empty
            <div class="p-6 text-sm text-gray-500">
              No orders yet. Start a project request when you are ready to begin.
            </div>
          @endforelse
        </div>
      </section>

      <section class="rounded-xl border border-gray-100 bg-white shadow-sm">
        <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
          <h2 class="text-base font-semibold text-gray-900">Subscription</h2>
          <a href="{{ route('subscription.plans') }}" class="text-sm font-medium text-primary hover:text-primary/80">View Plans</a>
        </div>
        <div class="p-6">
          @if($subscription)
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-semibold text-gray-900">{{ $subscription->plan->name }}</p>
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Support\ClientPortalDisplay::subscriptionStatusBadge($subscription->status) }}">
                    {{ \App\Support\ClientPortalDisplay::subscriptionStatusLabel($subscription->status) }}
                  </span>
                </div>
                <p class="mt-1 text-sm text-gray-500">€{{ $subscription->plan->price }} {{ $subscription->plan->billing_label }}</p>
                @if($subscription->next_invoice_at)
                  <p class="mt-1 text-xs text-gray-500">Next invoice: {{ $subscription->next_invoice_at->format('M j, Y') }}</p>
                @endif
                @if($subscription->cancel_requested)
                  <p class="mt-2 text-sm font-medium text-yellow-700">Cancellation requested. We will contact you shortly.</p>
                @endif
              </div>
              @if(! $subscription->cancel_requested && $subscription->status === 'active')
                <form method="POST" action="{{ route('subscription.cancel') }}">
                  @csrf
                  <button type="submit"
                          onclick="return confirm('Request subscription cancellation?')"
                          class="rounded-md border border-red-200 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                    Request Cancellation
                  </button>
                </form>
              @endif
            </div>
          @else
            <div class="rounded-lg border border-dashed border-gray-200 p-5">
              <h3 class="text-sm font-semibold text-gray-900">No active subscription</h3>
              <p class="mt-1 text-sm text-gray-500">Support plans are available when you need ongoing help.</p>
              <a href="{{ route('subscription.plans') }}" class="mt-4 inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">
                View Plans
              </a>
            </div>
          @endif
        </div>
      </section>
    </div>

    <section class="mt-6 rounded-xl border border-gray-100 bg-white shadow-sm">
      <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4">
        <div>
          <h2 class="text-base font-semibold text-gray-900">Digital Purchases</h2>
          <p class="mt-1 text-sm text-gray-500">Your latest product downloads and entitlement status.</p>
        </div>
      </div>
      <div class="divide-y divide-gray-100">
        @forelse($purchases as $purchase)
          @php
            $product = $purchase->version?->product;
            $downloadExpired = $purchase->download_expires_at && $purchase->download_expires_at->isPast();
            $canDownload = $purchase->download_limit > 0 && ! $downloadExpired;
          @endphp
          <article class="p-6">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
              <div class="min-w-0">
                @if($product?->is_published)
                  <a href="{{ route('shop.show', $product->slug) }}" class="font-semibold text-gray-900 hover:text-primary">{{ $product->name }}</a>
                @else
                  <p class="font-semibold text-gray-900">{{ $product?->name ?? 'Digital product' }}</p>
                @endif
                <p class="mt-1 text-sm text-gray-500">
                  Version {{ $purchase->version?->version_number ?? 'n/a' }} · Purchased {{ $purchase->created_at->format('M j, Y') }}
                  @if($purchase->amount)
                    · ${{ number_format($purchase->amount, 2) }}
                  @endif
                </p>
                <p class="mt-1 text-xs {{ $canDownload ? 'text-gray-500' : 'text-red-600' }}">
                  @if($downloadExpired)
                    Download access expired {{ $purchase->download_expires_at->format('M j, Y') }}.
                  @else
                    {{ $purchase->download_limit }} downloads remaining
                    @if($purchase->download_expires_at)
                      · access until {{ $purchase->download_expires_at->format('M j, Y') }}
                    @endif
                  @endif
                </p>
              </div>
              @if($canDownload)
                <form method="POST" action="{{ route('purchase.download', $purchase) }}">
                  @csrf
                  <button type="submit" class="inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-white hover:bg-primary/90">
                    Download
                  </button>
                </form>
              @else
                <span class="inline-flex h-9 items-center rounded-md border border-gray-200 px-4 text-sm font-medium text-gray-400">
                  Unavailable
                </span>
              @endif
            </div>
          </article>
        @empty
          <div class="p-6 text-sm text-gray-500">
            No digital purchases yet. Purchased files will appear here with protected download access.
          </div>
        @endforelse
      </div>
    </section>
  </div>
</main>
@endsection
