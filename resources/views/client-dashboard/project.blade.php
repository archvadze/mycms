@extends('layouts.main')
@section('title', $project->title . ' - Dashboard')

@section('content')
<main class="pt-24 pb-20 bg-gray-50 min-h-screen">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <div class="mb-8 flex items-start justify-between">
      <div>
        <div class="flex items-center gap-3 mb-2">
          <a href="{{ route('client-dashboard.index') }}"
             class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
            </svg>
            Dashboard
          </a>
          <span class="text-gray-300">/</span>
          <span class="text-sm text-gray-500">{{ $project->title }}</span>
        </div>
        <h1 class="text-3xl font-bold text-gray-900" style="letter-spacing:-0.02em">
          {{ $project->title }}
        </h1>
        <div class="flex items-center gap-4 mt-2">
          <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
            @if($project->status === 'completed') bg-green-100 text-green-800
            @elseif($project->status === 'in_progress') bg-blue-100 text-blue-800
            @elseif($project->status === 'review') bg-yellow-100 text-yellow-800
            @else bg-gray-100 text-gray-800 @endif">
            {{ ucfirst(str_replace('_', ' ', $project->status)) }}
          </span>
          @if($project->deadline)
            <span class="text-sm text-gray-500">
              Due: {{ \Carbon\Carbon::parse($project->deadline)->format('M j, Y') }}
            </span>
          @endif
          @if($project->price)
            <span class="text-sm text-gray-500">
              Price: ${{ number_format($project->price, 2) }}
            </span>
          @endif
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

      {{-- Main Content --}}
      <div class="lg:col-span-2 space-y-6">

        {{-- Description --}}
        @if($project->description)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-3">Project Description</h2>
          <p class="text-gray-600 leading-relaxed">{{ $project->description }}</p>
        </div>
        @endif

        {{-- Order Details --}}
        @if($project->order)
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-4">Order Details</h2>
          <dl class="grid grid-cols-2 gap-4">

            @if($project->order->domain)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Domain</dt>
              <dd class="text-sm font-medium text-gray-900">{{ $project->order->domain }}</dd>
            </div>
            @endif

            @if($project->order->website_type)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Website Type</dt>
              <dd class="text-sm text-gray-900">{{ ucfirst(str_replace('-', ' ', $project->order->website_type)) }}</dd>
            </div>
            @endif

            @if($project->order->timeline)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Timeline</dt>
              <dd class="text-sm text-gray-900">{{ ucfirst(str_replace('-', ' ', $project->order->timeline)) }}</dd>
            </div>
            @endif

            @if($project->order->budget_range)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Budget Range</dt>
              <dd class="text-sm text-gray-900">{{ str_replace('-', ' ', $project->order->budget_range) }}</dd>
            </div>
            @endif

            @if($project->order->price_estimate)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Estimated Total</dt>
              <dd class="text-sm font-bold text-gray-900">${{ number_format($project->order->price_estimate, 2) }}</dd>
            </div>
            @endif

            @if($project->order->payment_status)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide mb-1">Payment</dt>
              <dd>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                  {{ $project->order->payment_status === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' }}">
                  {{ ucfirst($project->order->payment_status) }}
                </span>
              </dd>
            </div>
            @endif

          </dl>

          @if($project->order->services->count() > 0)
          <div class="mt-4 pt-4 border-t border-gray-100">
            <dt class="text-xs text-gray-400 uppercase tracking-wide mb-2">Services</dt>
            <div class="flex flex-wrap gap-2">
              @foreach($project->order->services as $service)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                  {{ $service->name }}
                </span>
              @endforeach
            </div>
          </div>
          @endif

          @if($project->order->features->count() > 0)
          <div class="mt-4 pt-4 border-t border-gray-100">
            <dt class="text-xs text-gray-400 uppercase tracking-wide mb-2">Features</dt>
            <div class="flex flex-wrap gap-2">
              @foreach($project->order->features as $feature)
                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                  {{ $feature->name }}
                </span>
              @endforeach
            </div>
          </div>
          @endif

          @if($project->order->project_description)
          <div class="mt-4 pt-4 border-t border-gray-100">
            <dt class="text-xs text-gray-400 uppercase tracking-wide mb-2">Project Description</dt>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $project->order->project_description }}</p>
          </div>
          @endif

          @if($project->order->additional_requirements)
          <div class="mt-4 pt-4 border-t border-gray-100">
            <dt class="text-xs text-gray-400 uppercase tracking-wide mb-2">Additional Requirements</dt>
            <p class="text-sm text-gray-600 leading-relaxed">{{ $project->order->additional_requirements }}</p>
          </div>
          @endif

          {{-- Pay button if unpaid --}}
          @if($project->order->payment_status === 'unpaid')
          <div class="mt-4 pt-4 border-t border-gray-100">
            <a href="{{ route('payment.create', $project->order->id) }}"
               class="inline-flex items-center gap-2 rounded-md bg-yellow-500 hover:bg-yellow-600 text-white text-sm font-medium h-9 px-4">
              💳 Complete Payment — ${{ number_format($project->order->price_estimate, 2) }}
            </a>
          </div>
          @endif

        </div>
        @endif

        {{-- Messages --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
          <h2 class="text-lg font-semibold text-gray-900 mb-6">Messages</h2>

          {{-- Message Form --}}
          @if(session('success'))
            <div class="bg-green-50 border border-green-200 rounded-lg p-3 mb-4 text-sm text-green-800">
              {{ session('success') }}
            </div>
          @endif

          <form action="{{ route('client-dashboard.send-message', $project->id) }}" method="POST" class="mb-6">
            @csrf
            <div class="space-y-2">
              <label class="block text-sm font-medium text-gray-700">Send Message</label>
              <textarea name="message" rows="3" required
                class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring text-gray-900"
                placeholder="Type your message..."></textarea>
              @error('message')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            </div>
            <div class="mt-3">
              <button type="submit"
                class="inline-flex items-center justify-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-9 px-4">
                Send Message
              </button>
            </div>
          </form>

          {{-- Messages List --}}
          <div class="space-y-4">
            @forelse($messages as $message)
            <div class="flex items-start gap-3 {{ $message->sender_id === auth()->id() ? 'flex-row-reverse' : '' }}">
              <div class="flex-shrink-0 w-8 h-8 rounded-full bg-primary flex items-center justify-center">
                <span class="text-white text-xs font-bold">
                  {{ substr($message->sender->name ?? 'U', 0, 1) }}
                </span>
              </div>
              <div class="flex-1 {{ $message->sender_id === auth()->id() ? 'items-end' : '' }}">
                <div class="flex items-center gap-2 mb-1 {{ $message->sender_id === auth()->id() ? 'justify-end' : '' }}">
                  <span class="text-xs font-medium text-gray-900">{{ $message->sender->name ?? 'Unknown' }}</span>
                  <span class="text-xs text-gray-400">{{ $message->created_at->diffForHumans() }}</span>
                </div>
                <div class="inline-block px-4 py-2.5 rounded-2xl text-sm
                  {{ $message->sender_id === auth()->id()
                    ? 'bg-primary text-white rounded-tr-sm'
                    : 'bg-gray-100 text-gray-900 rounded-tl-sm' }}">
                  {{ $message->message }}
                </div>
              </div>
            </div>
            @empty
            <div class="text-center py-8">
              <p class="text-gray-400 text-sm">No messages yet. Start the conversation!</p>
            </div>
            @endforelse
          </div>

          <div class="mt-6">
            {{ $messages->links() }}
          </div>
        </div>
      </div>

      {{-- Sidebar --}}
      <div class="space-y-6">

        {{-- File Upload --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
          <h3 class="text-base font-semibold text-gray-900 mb-4">Upload File</h3>
          <form action="{{ route('client-dashboard.upload-file', $project->id) }}"
                method="POST" enctype="multipart/form-data" class="space-y-3" id="upload-form">
            @csrf
            <div id="drop-zone"
                 class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center cursor-pointer hover:border-primary hover:bg-primary/5 transition-all duration-200"
                 onclick="document.getElementById('file-input').click()">
              <svg class="mx-auto w-8 h-8 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              <p class="text-sm text-gray-500" id="drop-text">Drag & drop or <span class="text-primary font-medium">browse</span></p>
              <p class="text-xs text-gray-400 mt-1">PDF, images, Office, text, or ZIP. Max 10MB.</p>
            </div>
            <input type="file" name="file" id="file-input" required class="hidden">
            <div id="file-preview" class="hidden items-center gap-2 p-3 bg-gray-50 rounded-lg">
              <svg class="w-4 h-4 text-primary flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
              <span id="file-name" class="text-xs text-gray-700 truncate flex-1"></span>
              <button type="button" onclick="clearFile()" class="text-gray-400 hover:text-red-500">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            @error('file')<p class="text-red-500 text-xs">{{ $message }}</p>@enderror
            <button type="submit" id="upload-btn"
              class="w-full inline-flex justify-center items-center rounded-md bg-primary text-primary-foreground hover:bg-primary/90 text-sm font-medium h-9 px-4 disabled:opacity-50 disabled:cursor-not-allowed">
              Upload
            </button>
          </form>
        </div>
        {{-- Files List --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
          <h3 class="text-base font-semibold text-gray-900 mb-4">Project Files</h3>
          @if($files->count() > 0)
            <div class="space-y-2">
              @foreach($files as $file)
              <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                <div class="flex items-center gap-2 min-w-0">
                  <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
                  </svg>
                  <div class="min-w-0">
                    <p class="text-xs font-medium text-gray-900 truncate">Project file #{{ $file->id }}</p>
                    <p class="text-xs text-gray-400">{{ $file->created_at->diffForHumans() }}</p>
                  </div>
                </div>
                <a href="{{ route('client-dashboard.download-file', [$project->id, $file->id]) }}"
                   class="flex-shrink-0 text-xs text-primary hover:text-primary/80 font-medium ml-2">
                  Download
                </a>
              </div>
              @endforeach
            </div>
          @else
            <p class="text-gray-400 text-sm text-center py-4">No files yet</p>
          @endif
        </div>

        {{-- Project Info --}}
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
          <h3 class="text-base font-semibold text-gray-900 mb-4">Project Info</h3>
          <dl class="space-y-3">
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide">Status</dt>
              <dd class="mt-0.5">
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                  @if($project->status === 'completed') bg-green-100 text-green-800
                  @elseif($project->status === 'in_progress') bg-blue-100 text-blue-800
                  @elseif($project->status === 'review') bg-yellow-100 text-yellow-800
                  @else bg-gray-100 text-gray-800 @endif">
                  {{ ucfirst(str_replace('_', ' ', $project->status)) }}
                </span>
              </dd>
            </div>
            @if($project->price)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide">Price</dt>
              <dd class="text-sm font-semibold text-gray-900">${{ number_format($project->price, 2) }}</dd>
            </div>
            @endif
            @if($project->deadline)
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide">Deadline</dt>
              <dd class="text-sm font-semibold text-gray-900">
                {{ \Carbon\Carbon::parse($project->deadline)->format('M j, Y') }}
              </dd>
            </div>
            @endif
            <div>
              <dt class="text-xs text-gray-400 uppercase tracking-wide">Started</dt>
              <dd class="text-sm text-gray-900">{{ $project->created_at->format('M j, Y') }}</dd>
            </div>
          </dl>
        </div>

      </div>
    </div>
  </div>
</main>
@endsection

@push('scripts')
<script>
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const filePreview = document.getElementById('file-preview');
const fileName = document.getElementById('file-name');
const dropText = document.getElementById('drop-text');

fileInput.addEventListener('change', function() {
    if (this.files[0]) showFile(this.files[0]);
});

dropZone.addEventListener('dragover', function(e) {
    e.preventDefault();
    this.classList.add('border-primary', 'bg-primary/5');
});

dropZone.addEventListener('dragleave', function() {
    this.classList.remove('border-primary', 'bg-primary/5');
});

dropZone.addEventListener('drop', function(e) {
    e.preventDefault();
    this.classList.remove('border-primary', 'bg-primary/5');
    const file = e.dataTransfer.files[0];
    if (file) {
        const dt = new DataTransfer();
        dt.items.add(file);
        fileInput.files = dt.files;
        showFile(file);
    }
});

function showFile(file) {
    fileName.textContent = file.name;
    filePreview.classList.remove('hidden');
    filePreview.classList.add('flex');
    dropZone.classList.add('hidden');
}

function clearFile() {
    fileInput.value = '';
    filePreview.classList.add('hidden');
    filePreview.classList.remove('flex');
    dropZone.classList.remove('hidden');
}

// Upload progress
document.getElementById('upload-form').addEventListener('submit', function() {
    const btn = document.getElementById('upload-btn');
    btn.disabled = true;
    btn.textContent = 'Uploading...';
});
</script>
@endpush
