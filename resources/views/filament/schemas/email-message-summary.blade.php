@php
    // All mail fields may originate from external, untrusted senders.
    // Blade {{ }} escaping is required; never echo these with {!! !!}.
    $message = $record ?? null;
    $sender = $message
        ? (filled($message->from_name)
            ? $message->from_name . ' <' . $message->from_email . '>'
            : $message->from_email)
        : '—';
    $recipient = $message?->to_email ?: '—';
    $reply = $message?->replyRecipient() ?: '—';
    $subject = $message?->subject ?: '(No subject)';
    $received = $message?->received_at?->format('M j, Y H:i') ?: '—';
    $type = $message?->displayNotificationType() ?: '—';
@endphp
<style>
    .mycms-reader-summary{color:inherit;min-width:0}
    .mycms-reader-summary__subject{font-size:clamp(1.05rem,1.7vw,1.35rem);font-weight:720;line-height:1.35;overflow-wrap:anywhere;margin:0 0 1.15rem}
    .mycms-reader-summary__grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem 2rem}
    .mycms-reader-summary__label{font-size:.72rem;letter-spacing:.06em;font-weight:700;text-transform:uppercase;opacity:.65;margin-bottom:.28rem}
    .mycms-reader-summary__value{font-size:.95rem;font-weight:500;line-height:1.55;overflow-wrap:anywhere;word-break:normal}
    .mycms-reader-summary__tag{display:inline-block;border:1px solid rgba(130,130,130,.28);border-radius:20px;padding:.14rem .6rem;font-size:.8rem;font-weight:600}
    @@media(max-width:650px){.mycms-reader-summary__grid{grid-template-columns:1fr;gap:.85rem}.mycms-reader-summary__subject{margin-bottom:1rem}}
</style>
<div class="mycms-reader-summary">
    <h2 class="mycms-reader-summary__subject">{{ $subject }}</h2>
    <dl class="mycms-reader-summary__grid">
        <div><dt class="mycms-reader-summary__label">From</dt><dd class="mycms-reader-summary__value">{{ $sender }}</dd></div>
        <div><dt class="mycms-reader-summary__label">To</dt><dd class="mycms-reader-summary__value">{{ $recipient }}</dd></div>
        <div><dt class="mycms-reader-summary__label">Reply to</dt><dd class="mycms-reader-summary__value">{{ $reply }}</dd></div>
        <div><dt class="mycms-reader-summary__label">Received</dt><dd class="mycms-reader-summary__value">{{ $received }}</dd></div>
        <div><dt class="mycms-reader-summary__label">Type</dt><dd class="mycms-reader-summary__value"><span class="mycms-reader-summary__tag">{{ $type }}</span></dd></div>
    </dl>
</div>
