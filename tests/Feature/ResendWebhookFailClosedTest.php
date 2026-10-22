<?php

namespace Tests\Feature;

use Tests\TestCase;

class ResendWebhookFailClosedTest extends TestCase
{
    public function test_webhook_rejects_requests_when_secret_is_missing(): void
    {
        config()->set('resend.webhook.secret', null);

        $this->postJson('/resend/webhook', [
            'type' => 'email.received',
            'data' => ['email_id' => 'test-email'],
        ])->assertStatus(503);
    }

    public function test_webhook_rejects_invalid_signature_when_secret_is_configured(): void
    {
        config()->set(
            'resend.webhook.secret',
            'whsec_' . base64_encode(random_bytes(32))
        );

        $this->postJson('/resend/webhook', [
            'type' => 'email.received',
            'data' => ['email_id' => 'test-email'],
        ])->assertStatus(403);
    }

    public function test_unrelated_routes_are_not_blocked(): void
    {
        config()->set('resend.webhook.secret', null);

        \Illuminate\Support\Facades\Route::get(
            '/__test-webhook-unrelated',
            fn () => response('OK', 200)
        );

        $this->get('/__test-webhook-unrelated')->assertOk();
    }


    public function test_webhook_accepts_valid_signature(): void
    {
        [$payload, $timestamp, $headers] = $this->signedWebhook();

        $this->call(
            'POST', '/resend/webhook',
            [], [], [], $headers, $payload
        )->assertOk();
    }

    public function test_webhook_rejects_tampered_payload(): void
    {
        [$payload, $timestamp, $headers] = $this->signedWebhook();

        $tampered = json_encode([
            'type' => 'security.test',
            'data' => ['check' => 'modified'],
        ], JSON_THROW_ON_ERROR);

        $this->call(
            'POST', '/resend/webhook',
            [], [], [], $headers, $tampered
        )->assertForbidden();
    }

    public function test_webhook_rejects_expired_signature(): void
    {
        [$payload, $timestamp, $headers] = $this->signedWebhook(time() - 360);

        $this->call(
            'POST', '/resend/webhook',
            [], [], [], $headers, $payload
        )->assertForbidden();
    }

    private function signedWebhook(?int $timestamp = null): array
    {
        $secretBytes = random_bytes(32);

        config()->set(
            'resend.webhook.secret',
            'whsec_' . base64_encode($secretBytes)
        );

        $payload = json_encode([
            'type' => 'security.test',
            'data' => ['check' => 'original'],
        ], JSON_THROW_ON_ERROR);

        $messageId = 'msg_security_test_001';
        $timestamp ??= time();

        $signature = 'v1,' . base64_encode(
            hash_hmac(
                'sha256',
                "{$messageId}.{$timestamp}.{$payload}",
                $secretBytes,
                true
            )
        );

        return [
            $payload,
            $timestamp,
            [
                'HTTP_SVIX_ID' => $messageId,
                'HTTP_SVIX_TIMESTAMP' => (string) $timestamp,
                'HTTP_SVIX_SIGNATURE' => $signature,
                'HTTP_ACCEPT' => 'application/json',
                'CONTENT_TYPE' => 'application/json',
            ],
        ];
    }
}
