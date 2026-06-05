<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Reseller\ResellerAuthController as ResellerApiAuthController;
use App\Http\Controllers\Api\V1\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Controller;
use App\Services\Mobile\CustomerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single login endpoint for the unified mobile app (customer, staff, reseller).
 */
class UnifiedAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:staff,customer,reseller'],
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string', 'max:32'],
        ]);

        if ($data['role'] === 'reseller') {
            if (! config('reseller_portal.enabled', true)) {
                return response()->json(['message' => 'Reseller portal is disabled.'], 403);
            }

            $request->merge([
                'login' => $data['login'],
                'password' => $data['password'],
                'device_name' => $data['device_name'] ?? 'isp-radiant-app',
                'two_factor_code' => $data['two_factor_code'] ?? null,
            ]);

            $response = app(ResellerApiAuthController::class)->login($request, app(\App\Services\Resellers\ResellerTwoFactorService::class), app(\App\Services\Resellers\ResellerPortalDeviceTracker::class));
            $payload = $response->getData(true);
            if (! is_array($payload)) {
                return $response;
            }

            return response()->json(array_merge($payload, [
                'role' => 'reseller',
            ]), $response->getStatusCode());
        }

        if ($data['role'] === 'customer') {
            $request->merge([
                'login' => $data['login'],
                'password' => $data['password'],
                'device_name' => $data['device_name'] ?? 'isp-radiant-app',
            ]);

            $response = app(CustomerAuthController::class)->login($request, app(CustomerMobileService::class));
            $payload = $response->getData(true);
            if (is_array($payload)) {
                return response()->json(array_merge($payload, ['role' => 'customer']), $response->getStatusCode());
            }

            return $response;
        }

        if (! str_contains($data['login'], '@')) {
            return response()->json([
                'message' => 'Staff login: use your email address.',
            ], 422);
        }

        $request->merge([
            'email' => $data['login'],
            'password' => $data['password'],
            'device_name' => $data['device_name'] ?? 'isp-radiant-app',
        ]);

        $response = app(StaffAuthController::class)->login($request);
        $payload = $response->getData(true);
        if (is_array($payload)) {
            return response()->json(array_merge($payload, ['role' => 'staff']), $response->getStatusCode());
        }

        return $response;
    }
}
