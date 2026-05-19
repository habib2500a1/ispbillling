<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Return PostgreSQL connections to the pool after each HTTP request (PHP-FPM workers stay alive).
 */
final class DisconnectIdleDatabase
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            try {
                DB::disconnect();
            } catch (\Throwable) {
                // Ignore disconnect errors during shutdown.
            }
        }
    }
}
