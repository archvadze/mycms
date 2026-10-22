<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireResendWebhookSecret
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = trim((string) config('resend.path', 'resend'), '/');

        if (! $request->is($path . '/webhook')) {
            return $next($request);
        }

        if (! filled(config('resend.webhook.secret'))) {
            return response('Webhook unavailable', 503);
        }

        return $next($request);
    }
}
