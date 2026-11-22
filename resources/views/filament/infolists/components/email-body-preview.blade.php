@php
    $body = $getState();
    $body = is_array($body) ? $body : [];
@endphp

<x-dynamic-component :component="$getEntryWrapperView()" :entry="$entry">
    @include('filament.partials.email-body-preview', [
        'htmlBody' => $body['html'] ?? null,
        'textBody' => $body['text'] ?? null,
        'threadMode' => true,
    ])
</x-dynamic-component>
