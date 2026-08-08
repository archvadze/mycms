<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ThrottleResendWebhookTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear('resend-webhook:127.0.0.1');
    }

    public function test_resend_webhook_is_rate_limited_after_sixty_requests(): void
    {
        for ($i = 1; $i <= 60; $i++) {
            $response = $this->post('/resend/webhook');

            $this->assertNotSame(
                429,
                $response->getStatusCode()
            );
        }

        $response = $this->post('/resend/webhook');

        $response->assertStatus(429);
    }

    public function test_other_routes_are_not_affected_by_resend_rate_limit(): void
    {
        for ($i = 1; $i <= 65; $i++) {
            RateLimiter::hit(
                'resend-webhook:127.0.0.1',
                60
            );
        }

        $response = $this->get('/');

        $this->assertNotSame(
            429,
            $response->getStatusCode()
        );
    }
}
