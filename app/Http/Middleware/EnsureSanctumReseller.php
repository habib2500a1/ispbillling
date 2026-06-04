<?php

namespace App\Http\Middleware;

use App\Models\Reseller;
use App\Models\ResellerStaff;
use App\Support\ResellerApiContext;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
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

        $staff = $this->resolveStaffFromToken($request, $user);
        app(ResellerApiContext::class)->set($user, $staff);

        return $next($request);
    }

    private function resolveStaffFromToken(Request $request, Reseller $reseller): ?ResellerStaff
    {
        $token = $request->user()?->currentAccessToken();
        if (! $token instanceof PersonalAccessToken) {
            return null;
        }

        foreach ($token->abilities ?? [] as $ability) {
            if (! is_string($ability) || ! str_starts_with($ability, 'staff:')) {
                continue;
            }

            $staffId = (int) substr($ability, 6);
            if ($staffId <= 0) {
                continue;
            }

            return ResellerStaff::query()
                ->where('reseller_id', $reseller->id)
                ->whereKey($staffId)
                ->where('is_active', true)
                ->first();
        }

        return null;
    }
}
