<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Services\Resellers\ResellerPortalDeviceTracker;
use App\Services\Resellers\ResellerTwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ResellerAuthController extends Controller
{
    public function login(Request $request, ResellerTwoFactorService $twoFactor, ResellerPortalDeviceTracker $devices): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string', 'max:32'],
        ]);

        $reseller = Reseller::findForPortalLogin($data['login']);

        if (! $reseller || ! Hash::check($data['password'], (string) $reseller->portal_password)) {
            throw ValidationException::withMessages(['login' => ['Invalid credentials.']]);
        }

        if ($reseller->requiresTwoFactor()) {
            if (blank($data['two_factor_code'] ?? null) || ! $twoFactor->verify($reseller, (string) $data['two_factor_code'])) {
                return response()->json([
                    'message' => 'Two-factor code required.',
                    'requires_2fa' => true,
                ], 422);
            }
        }

        $devices->recordLogin($reseller, $request);

        $issuedAt = now();
        $abilities = ['reseller'];
        $token = $reseller->createToken($data['device_name'] ?? 'reseller-api', $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'auth_mode' => 'sanctum',
            'guard' => 'reseller',
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => null,
            'abilities' => $abilities,
            'reseller' => [
                'id' => $reseller->id,
                'code' => $reseller->code,
                'name' => $reseller->name,
                'tenant_id' => $reseller->tenant_id,
                'permissions' => $reseller->portalPermissions(),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Reseller $reseller */
        $reseller = $request->user();

        return response()->json([
            'reseller' => $reseller->only(['id', 'code', 'name', 'wallet_balance', 'franchise_type']),
            'permissions' => $reseller->portalPermissions(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
