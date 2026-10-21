<x-filament-panels::page>
    {{-- Scoped styles: do not depend on the application's separate Tailwind build. --}}
    <style>
        .mycms-sent-detail { display: grid; gap: 1.25rem; max-width: 1100px; margin-inline: auto; width: 100%; color: var(--fi-color, inherit); }
        .mycms-sent-card { border: 1px solid rgba(125,125,125,.24); border-radius: 14px; overflow: hidden; background: rgba(128,128,128,.035); }
        .mycms-sent-card__head { padding: 1.25rem 1.5rem; border-bottom: 1px solid rgba(125,125,125,.18); }
        .mycms-sent-card__body { padding: 1.5rem; }
        .mycms-sent-subject { font-size: clamp(1.2rem, 2vw, 1.55rem); font-weight: 700; line-height: 1.4; overflow-wrap: anywhere; margin: 0 0 .6rem; }
        .mycms-sent-muted { opacity: .7; font-size: .875rem; }
        .mycms-sent-heading { font-weight: 650; font-size: 1rem; margin: 0; }
        .mycms-sent-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.25rem 2rem; }
        .mycms-sent-field dt { font-size: .76rem; opacity: .7; font-weight: 650; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .35rem; }
        .mycms-sent-field dd { margin: 0; font-size: .95rem; overflow-wrap: anywhere; line-height: 1.55; }
        .mycms-sent-status { display: inline-block; border-radius: 999px; padding: .25rem .7rem; background: rgba(125,125,125,.15); font-size: .8rem; font-weight: 650; }
        .mycms-sent-status--sent { background: rgba(22,163,74,.13); color: #16803b; }
        .mycms-sent-status--unknown, .mycms-sent-status--failed { background: rgba(220,38,38,.12); color: #c02626; }
        .mycms-sent-body { white-space: pre-wrap; overflow-wrap: anywhere; line-height: 1.75; font-size: .96rem; min-height: 4.5rem; }
        .mycms-sent-attachment { display: flex; align-items: center; justify-content: space-between; gap: 1rem; padding: .85rem 0; border-bottom: 1px solid rgba(125,125,125,.18); }
        .mycms-sent-attachment:last-child { border-bottom: 0; }
        .mycms-sent-link { color: #2563eb; font-weight: 650; text-decoration: underline; }
        @media (max-width: 700px) {
            .mycms-sent-grid { grid-template-columns: 1fr; gap: 1rem; }
            .mycms-sent-card__head, .mycms-sent-card__body { padding: 1rem; }
            .mycms-sent-attachment { align-items: flex-start; flex-direction: column; }
        }
    </style>

    @php
        $message = $this->message;
        $to = $message->recipients ?: array_filter([$message->to_email]);
        $cc = $message->cc_recipients ?? [];
        $bcc = $message->bcc_recipients ?? [];
        $activity = $message->sent_at ?? $message->updated_at;
        $body = filled($message->text_body)
            ? $message->text_body
            : strip_tags($message->html_body ?? '');
        $attachments = $this->attachmentItems();
    @endphp

    <div class="mycms-sent-detail">
        <section class="mycms-sent-card" aria-label="Outgoing message summary">
            <div class="mycms-sent-card__head">
                <h2 class="mycms-sent-subject">{{ $message->subject ?: '(No subject)' }}</h2>
                <div class="mycms-sent-muted">Outgoing email #{{ $message->getKey() }}</div>
            </div>
            <div class="mycms-sent-card__body">
                <dl class="mycms-sent-grid">
                    <div class="mycms-sent-field">
                        <dt>From</dt>
                        <dd>{{ $message->from_name ? $message->from_name . ' <' . $message->from_email . '>' : $message->from_email }}</dd>
                    </div>
                    <div class="mycms-sent-field">
                        <dt>To</dt>
                        <dd>{{ implode(', ', $to) ?: '—' }}</dd>
                    </div>
                    <div class="mycms-sent-field">
                        <dt>CC</dt>
                        <dd>{{ implode(', ', $cc) ?: '—' }}</dd>
                    </div>
                    <div class="mycms-sent-field">
                        <dt>BCC</dt>
                        <dd>{{ implode(', ', $bcc) ?: '—' }}</dd>
                    </div>
                    <div class="mycms-sent-field">
                        <dt>Delivery status</dt>
                        <dd><span class="mycms-sent-status mycms-sent-status--{{ in_array($message->status, ['sent', 'unknown', 'failed'], true) ? $message->status : 'other' }}">{{ ucfirst($message->status ?: 'unknown') }}</span></dd>
                    </div>
                    <div class="mycms-sent-field">
                        <dt>{{ $message->sent_at ? 'Sent at' : 'Last activity' }}</dt>
                        <dd>{{ $activity?->format('M j, Y H:i') ?? '—' }}</dd>
                    </div>
                </dl>
            </div>
        </section>

        <section class="mycms-sent-card" aria-label="Message content">
            <div class="mycms-sent-card__head"><h3 class="mycms-sent-heading">Message</h3></div>
            <div class="mycms-sent-card__body">
                {{-- Blade escapes untrusted mail text; do NOT render with {!! !!}. --}}
                <div class="mycms-sent-body">{{ filled($body) ? $body : '(Empty message)' }}</div>
            </div>
        </section>

        @if (count($attachments) > 0)
            <section class="mycms-sent-card" aria-label="Attachments">
                <div class="mycms-sent-card__head"><h3 class="mycms-sent-heading">Attachments ({{ count($attachments) }})</h3></div>
                <div class="mycms-sent-card__body">
                    @foreach ($attachments as $attachment)
                        <div class="mycms-sent-attachment">
                            <span>{{ $attachment['filename'] }} <span class="mycms-sent-muted">({{ $attachment['size'] }})</span></span>
                            @if ($attachment['download_url'])
                                <a class="mycms-sent-link" href="{{ $attachment['download_url'] }}">Download</a>
                            @else
                                <span class="mycms-sent-muted">Unavailable or blocked</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
