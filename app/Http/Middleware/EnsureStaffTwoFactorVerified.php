<?php

namespace App\Http\Middleware;

use App\Filament\Pages\TwoFactorChallenge;
use App\Filament\Pages\TwoFactorSetup;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureStaffTwoFactorVerified
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Filament::auth()->user();
        if ($user === null) {
            return $next($request);
        }

        if (! $user->hasTwoFactorEnabled()) {
            session(['staff.2fa_verified' => true]);

            return $next($request);
        }

        if (session('staff.2fa_verified') === true) {
            return $next($request);
        }

        $route = $request->route()?->getName() ?? '';
        if (str_contains($route, 'two-factor-challenge') || str_contains($route, 'two-factor-setup')) {
            return $next($request);
        }

        if ($request->is('admin/two-factor-challenge') || $request->is('admin/two-factor-setup')) {
            return $next($request);
        }

        return redirect()->to(TwoFactorChallenge::getUrl());
    }
}
