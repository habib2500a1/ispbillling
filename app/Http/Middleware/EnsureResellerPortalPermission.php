<?php

namespace App\Http\Middleware;

use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureResellerPortalPermission
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $reseller = $request->user('reseller');

        if ($reseller === null) {
            return redirect()->route('reseller.login');
        }

        $portal = app(ResellerPortalSession::class);

        if (! $portal->canPortal($permission)) {
            abort(403, 'Your partner account does not have permission: '.(ResellerPortalPermission::labels()[$permission] ?? $permission));
        }

        return $next($request);
    }
}
