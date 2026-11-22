@include('filament.partials.email-body-preview', [
    'htmlBody' => $record?->html_body,
    'textBody' => $record?->text_body,
    'threadMode' => false,
])
