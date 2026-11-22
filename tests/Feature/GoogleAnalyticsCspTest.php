<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoogleAnalyticsCspTest extends TestCase
{
    use RefreshDatabase;

    public function test_ga4_and_turnstile_origins_are_allowed(): void
    {
        $response = $this->get('/contact');
        $response->assertOk();

        $csp = (string) $response->headers->get('Content-Security-Policy');
        $policies = [];

        foreach (explode(';', $csp) as $directive) {
            $parts = preg_split('/\s+/', trim($directive));

            if (! $parts || $parts[0] === '') {
                continue;
            }

            $name = array_shift($parts);
            $policies[$name] = $parts;
        }

        $this->assertContains(
            'https://www.googletagmanager.com',
            $policies['script-src'] ?? []
        );

        foreach ([
            'https://www.googletagmanager.com',
            'https://*.google-analytics.com',
            'https://*.google.com',
        ] as $origin) {
            $this->assertContains(
                $origin,
                $policies['connect-src'] ?? []
            );
        }

        foreach (['script-src', 'frame-src', 'connect-src'] as $directive) {
            $this->assertContains(
                'https://challenges.cloudflare.com',
                $policies[$directive] ?? []
            );
        }
    }
}
