<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Issue a new Sanctum token (refresh). Client should replace stored bearer token.
 */
class TokenRefreshController extends Controller
{
    public function refreshStaff(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return response()->json(['message' => 'Staff authentication required.'], 401);
        }

        $request->user()->currentAccessToken()?->delete();

        $abilities = ['staff'];
        if ($user->hasAnyRole(['super-admin', 'isp-admin', 'isp-engineer', 'isp-support', 'isp-manager'])) {
            $abilities[] = 'technician';
        }
        if ($user->hasAnyRole(['cashier', 'branch-manager', 'admin', 'super-admin', 'isp-admin'])) {
            $abilities[] = 'collector';
        }

        $expiresAt = now()->addDays((int) config('mobile.staff_token_expiry_days', 30));
        $token = $user->createToken(
            config('mobile.technician_token_name', 'isp-radiant-staff'),
            $abilities,
            $expiresAt,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }

    public function refreshCustomer(Request $request): JsonResponse
    {
        $customer = $request->user();
        if (! $customer instanceof Customer) {
            return response()->json(['message' => 'Customer authentication required.'], 401);
        }

        $customer->currentAccessToken()?->delete();

        $expiresAt = now()->addDays((int) config('mobile.customer_token_expiry_days', 90));
        $token = $customer->createToken(
            config('mobile.customer_token_name', 'isp-radiant-customer'),
            ['customer'],
            $expiresAt,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
        ]);
    }
}
