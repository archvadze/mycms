<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TurnstileCspTest extends TestCase
{
    use RefreshDatabase;
    public function test_contact_page_csp_allows_turnstile(): void
    {
        $response = $this->get('/contact');

        $response->assertOk();

        $csp = $response->headers->get('Content-Security-Policy');

        $this->assertNotNull($csp);

        foreach (['script-src', 'frame-src', 'connect-src'] as $directive) {
            $this->assertMatchesRegularExpression(
                '/(?:^|;\s*)' . preg_quote($directive, '/') .
                '\s+[^;]*https:\/\/challenges\.cloudflare\.com(?:;|$)/',
                $csp
            );
        }

        $this->assertStringContainsString('https://www.paypal.com', $csp);
        $this->assertStringContainsString('https://www.google.com', $csp);
    }
}
