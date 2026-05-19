<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSanctumCollector
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Staff authentication required.'], 403);
        }

        if (! $user->tokenCan('collector') && ! $user->hasAnyRole([
            'super-admin', 'isp-admin', 'admin', 'cashier', 'branch-manager',
        ])) {
            return response()->json(['message' => 'Collector access denied.'], 403);
        }

        return $next($request);
    }
}
