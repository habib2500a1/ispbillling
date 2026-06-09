<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Prevent CDN/browser from caching dynamic HTML (admin + public pay portal).
 */
final class PreventAdminPageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $noStore = ($request->is('admin', 'admin/*') && auth()->check())
            || $request->is('pay', 'pay/*');

        if ($noStore) {
            $response->headers->set('Cache-Control', 'private, no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('CDN-Cache-Control', 'no-store');
            $response->headers->set('Cloudflare-CDN-Cache-Control', 'no-store');
        }

        return $response;
    }
}
