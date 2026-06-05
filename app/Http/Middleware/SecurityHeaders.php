<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for admin, portal, and API responses.
 */
final class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self)');

        if ($request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if ($request->is('reseller') || $request->is('reseller/*')
            || $request->is('admin') || $request->is('admin/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
            $response->headers->set('CDN-Cache-Control', 'no-store');
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');
        }

        if ($request->is('reseller') || $request->is('reseller/*')) {
            $response->headers->set('X-Portal-Build', '2026.06.04-pro-qa2');
        }

        if ($request->is('admin') || $request->is('admin/*')) {
            $response->headers->set('X-Admin-Build', '2026.06.05-subscribers-livewire-v2');
        }

        return $response;
    }
}
