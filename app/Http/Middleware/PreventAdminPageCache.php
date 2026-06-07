<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent CDN/browser from caching authenticated admin HTML (Livewire pages).
 */
final class PreventAdminPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if ($request->is('admin', 'admin/*') && auth()->check()) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('CDN-Cache-Control', 'no-store');
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');
        }

        return $response;
    }
}
