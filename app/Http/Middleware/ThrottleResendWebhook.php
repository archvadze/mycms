<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleResendWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $resendPath = trim(
            (string) config('resend.path', 'resend'),
            '/'
        );

        $webhookPath = $resendPath . '/webhook';

        if (! $request->is($webhookPath)) {
            return $next($request);
        }

        $key = 'resend-webhook:' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, 60)) {
            return response('Too Many Requests', 429, [
                'Retry-After' => (string) RateLimiter::availableIn($key),
            ]);
        }

        RateLimiter::hit($key, 60);

        return $next($request);
    }
}