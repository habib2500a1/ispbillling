<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Reseller;
use App\Models\ResellerStaff;
use App\Services\Resellers\ResellerPortalActivityLogger;
use App\Services\Resellers\ResellerPortalDeviceTracker;
use App\Services\Resellers\ResellerTwoFactorService;
use App\Support\ResellerPortalSession;
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

        $staff = ResellerStaff::findForPortalLogin($data['login']);
        if ($staff !== null && Hash::check($data['password'], (string) $staff->password)) {
            return $this->issueStaffToken($staff, $request, $data, $devices);
        }

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

        return $this->issueOwnerToken($reseller, $data);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var Reseller $reseller */
        $reseller = $request->user();
        $portal = app(ResellerPortalSession::class);
        $staff = $portal->staff();

        return response()->json([
            'reseller' => $reseller->only(['id', 'code', 'name', 'wallet_balance', 'franchise_type', 'wallet_frozen']),
            'actor' => [
                'type' => $staff !== null ? 'staff' : 'owner',
                'name' => $portal->actorName(),
                'staff_id' => $staff?->id,
                'login' => $staff?->login,
            ],
            'permissions' => $staff !== null ? $staff->portalPermissions() : $reseller->portalPermissions(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function issueOwnerToken(Reseller $reseller, array $data): JsonResponse
    {
        $abilities = ['reseller'];
        $token = $reseller->createToken($data['device_name'] ?? 'reseller-api', $abilities)->plainTextToken;

        app(ResellerPortalActivityLogger::class)->log($reseller, 'api.login', meta: ['login' => $reseller->portalLoginId()]);

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'auth_mode' => 'sanctum',
            'guard' => 'reseller',
            'actor_type' => 'owner',
            'issued_at' => now()->toIso8601String(),
            'expires_at' => null,
            'abilities' => $abilities,
            'reseller' => [
                'id' => $reseller->id,
                'code' => $reseller->code,
                'name' => $reseller->name,
                'tenant_id' => $reseller->tenant_id,
            ],
            'permissions' => $reseller->portalPermissions(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function issueStaffToken(ResellerStaff $staff, Request $request, array $data, ResellerPortalDeviceTracker $devices): JsonResponse
    {
        $reseller = $staff->reseller;
        if ($reseller === null || ! $reseller->is_active || ! $reseller->hasPortalAccess()) {
            throw ValidationException::withMessages(['login' => ['Invalid credentials.']]);
        }

        $staff->recordLogin();
        $devices->recordLogin($reseller, $request);

        app(ResellerPortalActivityLogger::class)->log($reseller, 'api.login.staff', $staff, ['login' => $staff->login], $request);

        $abilities = ['reseller', 'staff:'.$staff->id];
        $token = $reseller->createToken($data['device_name'] ?? 'reseller-staff-api', $abilities)->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'auth_mode' => 'sanctum',
            'guard' => 'reseller',
            'actor_type' => 'staff',
            'issued_at' => now()->toIso8601String(),
            'expires_at' => null,
            'abilities' => $abilities,
            'reseller' => [
                'id' => $reseller->id,
                'code' => $reseller->code,
                'name' => $reseller->name,
                'tenant_id' => $reseller->tenant_id,
            ],
            'staff' => [
                'id' => $staff->id,
                'name' => $staff->name,
                'login' => $staff->login,
            ],
            'permissions' => $staff->portalPermissions(),
        ]);
    }
}
