<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // CSP — Filament-ს სჭირდება unsafe-inline
       $response->headers->set(
    'Content-Security-Policy',
    "default-src 'self'; " .
    "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.paypal.com https://www.sandbox.paypal.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com; " .
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdnjs.cloudflare.com; " .
    "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; " .
    "worker-src 'self' blob:; " .
    "img-src 'self' data: blob: https:; " .
    "frame-src https://www.paypal.com https://www.sandbox.paypal.com https://www.google.com; " .
    "connect-src 'self';"
);

        return $response;
    }
}
