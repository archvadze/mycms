<?php

return [
    'enabled' => (bool) env('CONTACT_TURNSTILE_ENABLED', false),
    'site_key' => env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    'contact_rate_limit' => max(1, (int) env('CONTACT_RATE_LIMIT', 5)),
    'contact_rate_window' => max(1, (int) env('CONTACT_RATE_WINDOW', 1)),
    'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
];
