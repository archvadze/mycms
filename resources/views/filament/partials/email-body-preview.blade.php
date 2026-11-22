@php
    // Both text_body and html_body are external, untrusted email content.
    $html = is_string($htmlBody ?? null) ? $htmlBody : '';
    $text = is_string($textBody ?? null) ? $textBody : '';
    $hasHtml = trim($html) !== '';
    $hasText = trim($text) !== '';
    $previewHeight = ($threadMode ?? false) ? 340 : 540;

    // The sandbox forbids scripts, forms, same-origin access, and top-level navigation.
    // This CSP additionally blocks remote images/tracking pixels and external resources.
    $previewCsp = "default-src 'none'; script-src 'none'; connect-src 'none'; "
        . "img-src data:; style-src 'unsafe-inline'; font-src data:; "
        . "base-uri 'none'; form-action 'none'; frame-src 'none'; object-src 'none'";
    $srcdoc = '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta http-equiv="Content-Security-Policy" content="' . e($previewCsp) . '">'
        . '<style>body{color:#222;background:#fff;margin:1rem;font:15px/1.6 system-ui,sans-serif;overflow-wrap:anywhere} table{max-width:100%} img{max-width:100%;height:auto}</style>'
        . '</head><body>' . $html . '</body></html>';
@endphp

<div style="width:100%;min-width:0;max-width:100%;">
    <div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:.5rem;margin-bottom:.75rem;">
        <span style="font-weight:650;font-size:.78rem;letter-spacing:.035em;opacity:.75;">{{ $hasHtml ? 'FORMATTED MESSAGE' : 'PLAIN TEXT MESSAGE' }}</span>
        @if($hasHtml)
            <span style="font-size:.75rem;opacity:.65;">Protected preview · remote images blocked</span>
        @endif
    </div>
    @if($hasHtml && (! $threadMode || ! $hasText))
        <div style="border:1px solid rgba(130,130,130,.22);border-radius:12px;overflow:hidden;background:#fff;box-shadow:0 4px 18px rgba(0,0,0,.055);">
        <iframe
            title="Email HTML preview"
            sandbox=""
            referrerpolicy="no-referrer"
            loading="lazy"
            srcdoc="{{ $srcdoc }}"
            style="display:block;width:100%;height:{{ $previewHeight }}px;border:0;background:#fff;"
        ></iframe>
        </div>
        @if($hasText)
            <details style="margin-top:.75rem">
                <summary style="cursor:pointer">Plain text version</summary>
                <div style="white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.65;margin-top:.5rem">{{ $text }}</div>
            </details>
        @endif
    @else
        <div style="white-space:pre-wrap;overflow-wrap:anywhere;line-height:1.75;padding:1.25rem;border:1px solid rgba(130,130,130,.22);border-radius:12px;">{{ $hasText ? $text : '(Empty message)' }}</div>
        @if($hasHtml)
            <details style="margin-top:.75rem">
                <summary style="cursor:pointer">Formatted HTML version</summary>
                <iframe
                    title="Thread message HTML preview"
                    sandbox=""
                    referrerpolicy="no-referrer"
                    loading="lazy"
                    srcdoc="{{ $srcdoc }}"
                    style="display:block;width:100%;height:{{ $previewHeight }}px;margin-top:.5rem;border:1px solid rgba(130,130,130,.22);border-radius:12px;background:#fff;"
                ></iframe>
            </details>
        @endif
    @endif
</div>
