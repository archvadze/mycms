@extends('layouts.main')
@section('title', $publication->title . ' - ' . config('agency.name') . ' Blog')
@section('description', $publication->excerpt ?? Str::limit(strip_tags($publication->content), 160))
@section('og_type', 'article')
@if($publication->cover_image)
@section('og_image', asset('storage/'.$publication->cover_image))
@endif

@section('content')
<main class="pt-24 pb-20">
  <article class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">

    {{-- Header --}}
    <header class="mb-10 text-center">
      @if($publication->categories->count() > 0)
        <div class="flex justify-center gap-2 mb-4">
          @foreach($publication->categories as $category)
            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-primary/10 text-primary">
              {{ $category->name }}
            </span>
          @endforeach
        </div>
      @endif

      <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 leading-tight" style="letter-spacing: -0.02em;">
        {{ $publication->title }}
      </h1>

      @if($publication->excerpt)
        <p class="text-xl text-gray-600 leading-relaxed max-w-2xl mx-auto mb-4">
          {{ $publication->excerpt }}
        </p>
      @endif

      <div class="flex items-center justify-center gap-3 text-sm text-gray-400">
        @if($publication->author)
          <span>By {{ $publication->author->name }}</span>
          <span>•</span>
        @endif
        @if($publication->published_at)
          <time>{{ $publication->published_at->format('M j, Y') }}</time>
        @endif
      </div>
    </header>

    {{-- Cover Image --}}
    @if($publication->cover_image)
    <div class="mb-10 rounded-xl overflow-hidden shadow-lg">
      <img src="{{ asset('storage/'.$publication->cover_image) }}" alt="{{ $publication->title }}"
           class="w-full h-72 object-cover">
    </div>
    @endif

    {{-- Content --}}
    <div class="prose prose-lg prose-gray max-w-none mb-12">
      {!! $publication->content !!}
    </div>

    {{-- Tags --}}
    @if($publication->tags->count() > 0)
    <div class="border-t border-gray-200 pt-8 mb-12">
      <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-4">Tags</h3>
      <div class="flex flex-wrap gap-2">
        @foreach($publication->tags as $tag)
          <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800 hover:bg-gray-200 transition-colors">
            {{ $tag->name }}
          </span>
        @endforeach
      </div>
    </div>
    @endif
    
    {{-- Social Share --}}
    <div class="border-t border-gray-200 pt-8 mb-12">
      <h3 class="text-sm font-medium text-gray-500 uppercase tracking-wide mb-4">Share</h3>
      <div class="flex flex-wrap gap-3" x-data="{ copied: false }">

        {{-- Facebook --}}
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#1877F2] text-white text-sm font-medium hover:bg-[#1877F2]/90 transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
          </svg>
          Facebook
        </a>

        {{-- X (Twitter) --}}
        <a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($publication->title) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-black text-white text-sm font-medium hover:bg-black/80 transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.737-8.835L1.254 2.25H8.08l4.253 5.622 5.911-5.622zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>
          </svg>
          X
        </a>

        {{-- LinkedIn --}}
        <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url()->current()) }}"
           target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#0A66C2] text-white text-sm font-medium hover:bg-[#0A66C2]/90 transition-colors">
          <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
            <path d="M20.447 20.452h-3.554v-5.569c0-1.328-.027-3.037-1.852-3.037-1.853 0-2.136 1.445-2.136 2.939v5.667H9.351V9h3.414v1.561h.046c.477-.9 1.637-1.85 3.37-1.85 3.601 0 4.267 2.37 4.267 5.455v6.286zM5.337 7.433a2.062 2.062 0 01-2.063-2.065 2.064 2.064 0 112.063 2.065zm1.782 13.019H3.555V9h3.564v11.452zM22.225 0H1.771C.792 0 0 .774 0 1.729v20.542C0 23.227.792 24 1.771 24h20.451C23.2 24 24 23.227 24 22.271V1.729C24 .774 23.2 0 22.222 0h.003z"/>
          </svg>
          LinkedIn
        </a>

        {{-- Copy Link --}}
        <button @click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2000)"
                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors">
          <svg x-show="!copied" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/>
          </svg>
          <svg x-show="copied" class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
          </svg>
          <span x-text="copied ? 'Copied!' : 'Copy Link'"></span>
        </button>

      </div>
    </div>

    {{-- Comments --}}
    {{-- Custom Comments Section --}}
<div class="mt-16 border-t border-gray-100 pt-10 mb-12">
    <h3 class="text-2xl font-bold text-gray-900 mb-8">Comments ({{ $publication->comments_count }})</h3>

    {{-- Comment Form --}}
@auth
    {{-- ფორმა მხოლოდ ავტორიზებულებისთვის --}}
    <form action="{{ route('comments.store', $publication) }}" method="POST" class="mb-12 bg-gray-50 p-6 rounded-xl">
        @csrf
        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-2">Leave a comment as {{ auth()->user()->name }}</label>
            <textarea name="content" rows="3" required placeholder="Write your thoughts..."
                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary"></textarea>
        </div>
        <button type="submit" class="bg-primary text-white px-6 py-2 rounded-lg font-medium hover:bg-primary/90 transition">
            Post Comment
        </button>
    </form>
@else
    {{-- შეტყობინება სტუმრებისთვის --}}
    <div class="mb-12 bg-blue-50 p-6 rounded-xl text-center">
        <p class="text-gray-700">To leave a comment, please follow the link:
 <a href="{{ route('login') }}" class="text-primary font-bold underline">login</a>.</p>
    </div>
@endauth

    {{-- Comments List --}}
    <div class="space-y-8" x-data="{ replyTo: null }">
        @foreach($publication->approvedComments as $comment)
            <div class="flex gap-4">
                <div class="flex-shrink-0">
                    @if($comment->user?->avatar)
                        <img src="{{ asset('avatars/' . $comment->user->avatar) }}"
                             alt="{{ $comment->user->name }}"
                             class="w-10 h-10 rounded-full object-cover">
                    @else
                        <div class="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center font-bold text-primary">
                            {{ substr($comment->user ? $comment->user->name : $comment->user_name, 0, 1) }}
                        </div>
                    @endif
                </div>
                <div class="flex-1">
                    <div class="flex items-center gap-2 mb-1 flex-wrap">
                        <span class="font-bold text-gray-900">{{ $comment->user ? $comment->user->name : $comment->user_name }}</span>
                        @if($comment->user)
                            <span class="text-xs text-gray-400">წევრია {{ $comment->user->created_at->format('Y') }} წლიდან</span>
                            <span class="text-gray-300">•</span>
                        @endif
                        <span class="text-xs text-gray-400">{{ $comment->created_at->diffForHumans() }}</span>
                    </div>
                    @if($comment->user?->bio)
                        <p class="text-xs text-gray-400 mb-1 italic">{{ Str::limit($comment->user->bio, 80) }}</p>
                    @endif
                    @if($comment->user?->tags)
                        <div class="flex flex-wrap gap-1 mb-2">
                            @foreach(array_slice($comment->user->tags, 0, 3) as $tag)
                                <span class="text-xs px-2 py-0.5 rounded-full bg-primary/10 text-primary">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                    <p class="text-gray-600 leading-relaxed">{{ $comment->content }}</p>

                    {{-- Reply button --}}
                    @auth
                    <button @click="replyTo = replyTo === {{ $comment->id }} ? null : {{ $comment->id }}"
                            class="mt-2 text-xs text-primary hover:text-primary/80 font-medium">
                        ↩ Reply
                    </button>

                    {{-- Reply form --}}
                    <div x-show="replyTo === {{ $comment->id }}" x-cloak class="mt-3">
                        <form action="{{ route('comments.store', $publication) }}" method="POST"
                              class="bg-gray-50 p-4 rounded-lg"
                              id="reply-form-{{ $comment->id }}"
                              x-data="{ mention: '{{ e($comment->user?->name ?? $comment->user_name) }}', mentionId: {{ $comment->user_id }} }">
                            @csrf
                            <input type="hidden" name="parent_id" value="{{ $comment->id }}">
                            <input type="hidden" name="reply_to_user_id" :value="mentionId">
                            <div x-show="mention" class="flex items-center gap-2 mb-2">
                                <span class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium bg-primary/10 text-primary">
                                    <span>@</span><span x-text="mention"></span>
                                    <button type="button" @click="mention = ''; mentionId = null"
                                            class="hover:text-red-500 ml-1 font-bold">x</button>
                                </span>
                            </div>
                            <textarea name="content" rows="2" required
                                      id="reply-textarea-{{ $comment->id }}"
                                      class="w-full rounded-lg border-gray-300 shadow-sm focus:border-primary focus:ring-primary text-sm mb-2"
                                      placeholder="Write a reply..."></textarea>
                            <div class="flex gap-2">
                                <button type="submit" class="bg-primary text-white px-4 py-1.5 rounded-lg text-sm font-medium hover:bg-primary/90 transition">
                                    Post Reply
                                </button>
                                <button type="button" @click="replyTo = null"
                                        class="px-4 py-1.5 rounded-lg text-sm text-gray-500 hover:bg-gray-200 transition">
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </div>
                    @endauth

                    {{-- Replies --}}
                    @if($comment->replies && $comment->replies->count() > 0)
                    <div class="mt-4 space-y-4 pl-4 border-l-2 border-primary/20">
                        @foreach($comment->replies as $reply)
                        <div class="flex gap-3">
                            <div class="flex-shrink-0">
                                @if($reply->user?->avatar)
                                    <img src="{{ asset('avatars/' . $reply->user->avatar) }}"
                                         alt="{{ $reply->user->name }}"
                                         class="w-8 h-8 rounded-full object-cover">
                                @else
                                    <div class="w-8 h-8 rounded-full bg-primary/10 flex items-center justify-center text-xs font-bold text-primary">
                                        {{ substr($reply->user ? $reply->user->name : $reply->user_name, 0, 1) }}
                                    </div>
                                @endif
                            </div>
                            <div>
                                <div class="flex items-center gap-2 mb-1 flex-wrap">
                                    <span class="font-bold text-gray-900 text-sm">{{ $reply->user ? $reply->user->name : $reply->user_name }}</span>
                                    @if($reply->user)
                                        <span class="text-xs text-gray-400">წევრია {{ $reply->user->created_at->format('Y') }} წლიდან</span>
                                        <span class="text-gray-300">•</span>
                                    @endif
                                    <span class="text-xs text-gray-400">{{ $reply->created_at->diffForHumans() }}</span>
                                </div>
                                {{-- Quote box — რომელ კომენტარს გაეცა პასუხი --}}
                                @if($reply->replyToUser && $reply->replyToUser->id !== $comment->user_id)
                                <div class="mb-2 pl-3 border-l-2 border-gray-300 bg-gray-50 rounded-r-lg py-1.5 pr-2">
                                    <span class="text-xs font-semibold text-gray-500">{{ $reply->replyToUser->name }}</span>
                                    @php
                                        $quotedReply = $comment->replies->firstWhere('user_id', $reply->reply_to_user_id);
                                    @endphp
                                    @if($quotedReply)
                                    <p class="text-xs text-gray-400 mt-0.5 truncate">{{ Str::limit($quotedReply->content, 80) }}</p>
                                    @endif
                                </div>
                                @endif
                                <p class="text-gray-600 text-sm leading-relaxed">{{ $reply->content }}</p>
                                {{-- Reply to reply button --}}
                                @auth
                                <button
                                    x-on:click="
                                        replyTo = {{ $comment->id }};
                                        $nextTick(() => {
                                            const form = document.getElementById('reply-form-{{ $comment->id }}');
                                            if (form && form._x_dataStack) {
                                                form._x_dataStack[0].mention = '{{ e($reply->user?->name ?? $reply->user_name) }}';
                                                form._x_dataStack[0].mentionId = {{ $reply->user_id }};
                                            }
                                            const ta = document.getElementById('reply-textarea-{{ $comment->id }}');
                                            if (ta) { ta.focus(); }
                                        });
                                    "
                                    class="mt-1 text-xs text-gray-400 hover:text-primary font-medium">
                                    ↩ Reply
                                </button>
                                @endauth
                            </div>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>

    {{-- Back --}}
    <div class="text-center">
      <a href="{{ route('blog') }}"
         class="inline-flex items-center px-6 py-3 text-base font-medium rounded-md text-white bg-primary hover:bg-primary/90 transition-colors">
        <svg class="mr-2 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Back to Blog
      </a>
    </div>

  </article>
</main>
@endsection
