<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSanctumReseller
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof Reseller) {
            return response()->json(['message' => 'Reseller authentication required.'], 403);
        }

        if (! $user->is_active) {
            return response()->json(['message' => 'Partner account is inactive.'], 403);
        }

        return $next($request);
    }
}
