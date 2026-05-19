<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSanctumTechnician
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

        if (! $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-engineer', 'isp-support', 'isp-manager'])) {
            return response()->json(['message' => 'Technician access denied.'], 403);
        }

        return $next($request);
    }
}
