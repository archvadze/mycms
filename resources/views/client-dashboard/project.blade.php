@extends('layouts.main')
@section('title', $project->title . ' - Dashboard')

@section('content')
<main class="pt-24 pb-20 bg-gray-50 min-h-screen">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

    <div class="mb-8">
      <a href="{{ route('client-dashboard.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-gray-500 hover:text-gray-700">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Dashboard
      </a>
      <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900">{{ $project->title }}</h1>
          @if($project->description)
            <p class="mt-2 max-w-3xl text-sm leading-6 text-gray-500">{{ $project->description }}</p>
          @endif
        </div>
        <span class="inline-flex w-fit items-center rounded-full px-3 py-1 text-sm font-medium {{ \App\Support\ClientPortalDisplay::projectStatusBadge($project->status) }}">
          {{ \App\Support\ClientPortalDisplay::projectStatusLabel($project->status) }}
        </span>
      </div>
    </div>

    @if(session('success'))
      <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-sm text-green-800">
        {{ session('success') }}
      </div>
    @endif

    <section class="mb-6 rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
      <h2 class="text-lg font-semibold text-gray-900">Project Summary</h2>
      <div class="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-4">
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Status</p>
          <p class="mt-1 text-sm font-semibold text-gray-900">{{ \App\Support\ClientPortalDisplay::projectStatusLabel($project->status) }}</p>
        </div>
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Progress</p>
          <p class="mt-1 text-sm font-semibold text-gray-900">{{ $project->progress ?? 0 }}%</p>
        </div>
        @if($project->deadline)
          <div>
            <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Deadline</p>
            <p class="mt-1 text-sm font-semibold text-gray-900">{{ $project->deadline->format('M j, Y') }}</p>
          </div>
        @endif
        <div>
          <p class="text-xs font-medium uppercase tracking-wide text-gray-400">Started</p>
          <p class="mt-1 text-sm font-semibold text-gray-900">{{ $project->created_at->format('M j, Y') }}</p>
        </div>
      </div>
      <div class="mt-5 h-2 w-full rounded-full bg-gray-100">
        <div class="{{ $project->progressColor }} h-2 rounded-full" style="width: {{ min(100, max(0, $project->progress ?? 0)) }}%"></div>
      </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
      <div class="space-y-6 lg:col-span-2">
        @if($project->order)
          <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
              <div>
                <h2 class="text-lg font-semibold text-gray-900">Order Details</h2>
                <p class="mt-1 text-sm text-gray-500">Request details connected to this project.</p>
              </div>
              <div class="flex flex-wrap gap-2">
                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Support\ClientPortalDisplay::orderStatusBadge($project->order->status) }}">
                  {{ \App\Support\ClientPortalDisplay::orderStatusLabel($project->order->status) }}
                </span>
                @if($project->order->payment_status)
                  <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ \App\Support\ClientPortalDisplay::paymentStatusBadge($project->order->payment_status) }}">
                    {{ \App\Support\ClientPortalDisplay::paymentStatusLabel($project->order->payment_status) }}
                  </span>
                @endif
              </div>
            </div>

            <dl class="mt-5 grid gap-4 sm:grid-cols-2">
              @if($project->order->domain)
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Domain</dt>
                  <dd class="mt-1 text-sm font-medium text-gray-900">{{ $project->order->domain }}</dd>
                </div>
              @endif
              @if($project->order->website_type)
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Website Type</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ Str::of($project->order->website_type)->replace('-', ' ')->title() }}</dd>
                </div>
              @endif
              @if($project->order->timeline)
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Timeline</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ Str::of($project->order->timeline)->replace('-', ' ')->title() }}</dd>
                </div>
              @endif
              @if($project->order->budget_range)
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Budget Range</dt>
                  <dd class="mt-1 text-sm text-gray-900">{{ Str::of($project->order->budget_range)->replace('-', ' ')->title() }}</dd>
                </div>
              @endif
              @if($project->order->price_estimate)
                <div>
                  <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">Estimated Total</dt>
                  <dd class="mt-1 text-sm font-semibold text-gray-900">${{ number_format($project->order->price_estimate, 2) }}</dd>
                </div>
              @endif
            </dl>

            @if($project->order->services->count() > 0)
              <div class="mt-5 border-t border-gray-100 pt-5">
                <h3 class="text-xs font-medium uppercase tracking-wide text-gray-400">Services</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                  @foreach($project->order->services as $service)
                    <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-medium text-primary">{{ $service->name }}</span>
                  @endforeach
                </div>
              </div>
            @endif

            @if($project->order->features->count() > 0)
              <div class="mt-5 border-t border-gray-100 pt-5">
                <h3 class="text-xs font-medium uppercase tracking-wide text-gray-400">Features</h3>
                <div class="mt-2 flex flex-wrap gap-2">
                  @foreach($project->order->features as $feature)
                    <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-700">{{ $feature->name }}</span>
                  @endforeach
                </div>
              </div>
            @endif

            @if($project->order->project_description || $project->order->additional_requirements)
              <div class="mt-5 space-y-4 border-t border-gray-100 pt-5">
                @if($project->order->project_description)
                  <div>
                    <h3 class="text-xs font-medium uppercase tracking-wide text-gray-400">Project Description</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $project->order->project_description }}</p>
                  </div>
                @endif
                @if($project->order->additional_requirements)
                  <div>
                    <h3 class="text-xs font-medium uppercase tracking-wide text-gray-400">Additional Requirements</h3>
                    <p class="mt-2 text-sm leading-6 text-gray-600">{{ $project->order->additional_requirements }}</p>
                  </div>
                @endif
              </div>
            @endif

            @if($project->order->payment_status === 'unpaid')
              <div class="mt-5 border-t border-gray-100 pt-5">
                <a href="{{ route('payment.create', $project->order->id) }}"
                   class="inline-flex h-9 items-center rounded-md bg-yellow-500 px-4 text-sm font-medium text-white hover:bg-yellow-600">
                  Complete Payment
                </a>
              </div>
            @endif
          </section>
        @endif

        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
          <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
            <div>
              <h2 class="text-lg font-semibold text-gray-900">Conversation</h2>
              <p class="mt-1 text-sm text-gray-500">Messages are shown oldest first. Up to 50 messages are shown per page.</p>
            </div>
          </div>

          <form action="{{ route('client-dashboard.send-message', $project->id) }}" method="POST" class="mt-6 rounded-lg border border-gray-100 bg-gray-50 p-4">
            @csrf
            <label for="project-message" class="block text-sm font-medium text-gray-700">Message the project team</label>
            <p class="mt-1 text-xs text-gray-500">Use this for project questions, files you plan to upload, or feedback. Maximum 1000 characters.</p>
            <textarea id="project-message" name="message" rows="4" required maxlength="1000"
              class="mt-3 flex min-h-[100px] w-full rounded-md border border-input bg-white px-3 py-2 text-sm text-gray-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              placeholder="Write your message to the team...">{{ old('message') }}</textarea>
            @error('message')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="submit" class="mt-3 inline-flex h-9 items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90">
              Send to Team
            </button>
          </form>

          <div class="mt-6 space-y-4">
            @forelse($messages as $message)
              @php $isClientMessage = $message->sender_id === auth()->id(); @endphp
              <article class="flex items-start gap-3 {{ $isClientMessage ? 'flex-row-reverse' : '' }}">
                <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-full {{ $isClientMessage ? 'bg-primary' : 'bg-gray-800' }}">
                  <span class="text-xs font-bold text-white">{{ Str::substr($message->sender?->name ?? 'T', 0, 1) }}</span>
                </div>
                <div class="max-w-[85%] {{ $isClientMessage ? 'text-right' : '' }}">
                  <div class="mb-1 flex flex-wrap items-center gap-2 {{ $isClientMessage ? 'justify-end' : '' }}">
                    <span class="text-xs font-medium text-gray-900">{{ $isClientMessage ? 'You' : ($message->sender?->name ?? 'Project Team') }}</span>
                    <span class="text-xs text-gray-400">{{ $message->created_at->format('M j, Y g:i A') }}</span>
                  </div>
                  <div class="inline-block rounded-2xl px-4 py-2.5 text-left text-sm leading-6 {{ $isClientMessage ? 'rounded-tr-sm bg-primary text-white' : 'rounded-tl-sm bg-gray-100 text-gray-900' }}">
                    {{ $message->message }}
                  </div>
                </div>
              </article>
            @empty
              <div class="rounded-lg border border-dashed border-gray-200 p-8 text-center">
                <h3 class="text-sm font-semibold text-gray-900">No messages yet</h3>
                <p class="mt-1 text-sm text-gray-500">Send a message when you have a question or update for the project team.</p>
              </div>
            @endforelse
          </div>

          @if($messages->hasPages())
            <div class="mt-6">
              {{ $messages->links() }}
            </div>
          @endif
        </section>
      </div>

      <aside class="space-y-6">
        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
          <h2 class="text-base font-semibold text-gray-900">Upload File</h2>
          <p class="mt-1 text-sm text-gray-500">Allowed: PDF, images, Office documents, text files, or ZIP archives up to 10 MB.</p>

          <form action="{{ route('client-dashboard.upload-file', $project->id) }}" method="POST" enctype="multipart/form-data" class="mt-4 space-y-3" id="upload-form">
            @csrf
            <label id="drop-zone" for="file-input"
              class="block cursor-pointer rounded-xl border-2 border-dashed border-gray-200 p-6 text-center transition hover:border-primary hover:bg-primary/5">
              <svg class="mx-auto mb-2 h-8 w-8 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              <span class="text-sm text-gray-500" id="drop-text">Choose a file or drag it here</span>
              <span class="mt-1 block text-xs text-gray-400">Maximum size: 10 MB</span>
            </label>
            <input type="file" name="file" id="file-input" required class="sr-only" aria-describedby="file-help">
            <p id="file-help" class="text-xs text-gray-500">Accepted extensions: pdf, jpg, jpeg, png, webp, txt, doc, docx, xls, xlsx, zip.</p>
            <div id="file-preview" class="hidden items-center gap-2 rounded-lg bg-gray-50 p-3">
              <svg class="h-4 w-4 flex-shrink-0 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
              <span id="file-name" class="min-w-0 flex-1 truncate text-xs text-gray-700"></span>
              <button type="button" onclick="clearFile()" class="text-gray-400 hover:text-red-500" aria-label="Remove selected file">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
              </button>
            </div>
            @error('file')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            <button type="submit" id="upload-btn" class="inline-flex h-9 w-full items-center justify-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:cursor-not-allowed disabled:opacity-50">
              Upload File
            </button>
          </form>
        </section>

        <section class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
          <h2 class="text-base font-semibold text-gray-900">Files</h2>
          <p class="mt-1 text-sm text-gray-500">Download shared project files securely.</p>
          <div class="mt-4 space-y-2">
            @forelse($files as $file)
              <div class="rounded-lg border border-gray-100 bg-gray-50 p-3">
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-medium text-gray-900">{{ \App\Support\ClientPortalDisplay::safeFilename($file->file_path) }}</p>
                    <p class="mt-1 text-xs text-gray-500">
                      Uploaded {{ $file->created_at->format('M j, Y') }}
                      @if($file->uploader)
                        by {{ $file->uploader->id === auth()->id() ? 'you' : $file->uploader->name }}
                      @endif
                    </p>
                  </div>
                  <a href="{{ route('client-dashboard.download-file', [$project->id, $file->id]) }}"
                     class="flex-shrink-0 text-sm font-medium text-primary hover:text-primary/80">
                    Download
                  </a>
                </div>
              </div>
            @empty
              <div class="rounded-lg border border-dashed border-gray-200 p-5 text-sm text-gray-500">
                No files yet. Upload briefs, assets, or feedback files when the team needs them.
              </div>
            @endforelse
          </div>
        </section>
      </aside>
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

fileInput.addEventListener('change', function () {
    if (this.files[0]) showFile(this.files[0]);
});

dropZone.addEventListener('dragover', function (event) {
    event.preventDefault();
    this.classList.add('border-primary', 'bg-primary/5');
});

dropZone.addEventListener('dragleave', function () {
    this.classList.remove('border-primary', 'bg-primary/5');
});

dropZone.addEventListener('drop', function (event) {
    event.preventDefault();
    this.classList.remove('border-primary', 'bg-primary/5');
    const file = event.dataTransfer.files[0];

    if (file) {
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;
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

document.getElementById('upload-form').addEventListener('submit', function () {
    const button = document.getElementById('upload-btn');
    button.disabled = true;
    button.textContent = 'Uploading...';
});
</script>
@endpush
