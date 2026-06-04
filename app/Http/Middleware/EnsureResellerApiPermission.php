<?php

namespace App\Http\Middleware;

use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerApiPermission
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        if (! app(ResellerPortalSession::class)->canPortal($permission)) {
            return response()->json([
                'message' => 'Permission denied.',
                'permission' => $permission,
                'label' => ResellerPortalPermission::labels()[$permission] ?? $permission,
            ], 403);
        }

        return $next($request);
    }
}
