<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Reseller\ResellerAuthController as ResellerApiAuthController;
use App\Http\Controllers\Api\V1\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Controller;
use App\Services\Mobile\CustomerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Single login endpoint for the unified mobile app (customer, staff, reseller).
 */
class UnifiedAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['nullable', 'string', 'in:staff,customer,reseller,auto'],
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'two_factor_code' => ['nullable', 'string', 'max:32'],
        ]);

        $role = $data['role'] ?? 'auto';
        if ($role === 'auto') {
            return $this->loginAuto($request, $data);
        }

        return match ($role) {
            'reseller' => $this->loginReseller($request, $data),
            'customer' => $this->loginCustomer($request, $data),
            default => $this->loginStaff($request, $data),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function loginAuto(Request $request, array $data): JsonResponse
    {
        $login = (string) $data['login'];

        if (str_contains($login, '@')) {
            $staff = $this->tryStaff($request, $data);
            if ($staff !== null) {
                return $staff;
            }
        }

        $customer = $this->tryCustomer($request, $data);
        if ($customer !== null) {
            return $customer;
        }

        $reseller = $this->tryReseller($request, $data);
        if ($reseller !== null) {
            return $reseller;
        }

        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tryStaff(Request $request, array $data): ?JsonResponse
    {
        if (! str_contains((string) $data['login'], '@')) {
            return null;
        }

        try {
            $response = $this->loginStaff($request, $data);
            if ($response->getStatusCode() === 401) {
                return null;
            }

            return $response;
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tryCustomer(Request $request, array $data): ?JsonResponse
    {
        try {
            $response = $this->loginCustomer($request, $data);
            if ($response->getStatusCode() === 401) {
                return null;
            }

            return $response;
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tryReseller(Request $request, array $data): ?JsonResponse
    {
        try {
            $response = $this->loginReseller($request, $data);
            $status = $response->getStatusCode();
            if (in_array($status, [401, 422], true)) {
                $payload = $response->getData(true);
                if (is_array($payload) && ($payload['requires_2fa'] ?? false)) {
                    return response()->json(array_merge($payload, [
                        'role' => 'reseller',
                    ]), 422);
                }

                return null;
            }

            return $response;
        } catch (ValidationException) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function loginReseller(Request $request, array $data): JsonResponse
    {
        if (! config('reseller_portal.enabled', true)) {
            return response()->json(['message' => 'Reseller portal is disabled.'], 403);
        }

        $request->merge([
            'login' => $data['login'],
            'password' => $data['password'],
            'device_name' => $data['device_name'] ?? 'isp-radiant-app',
            'two_factor_code' => $data['two_factor_code'] ?? null,
        ]);

        $response = app(ResellerApiAuthController::class)->login(
            $request,
            app(\App\Services\Resellers\ResellerTwoFactorService::class),
            app(\App\Services\Resellers\ResellerPortalDeviceTracker::class)
        );
        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return $response;
        }

        return response()->json(array_merge($payload, [
            'role' => 'reseller',
        ]), $response->getStatusCode());
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function loginCustomer(Request $request, array $data): JsonResponse
    {
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

    /**
     * @param  array<string, mixed>  $data
     */
    private function loginStaff(Request $request, array $data): JsonResponse
    {
        if (! str_contains((string) $data['login'], '@')) {
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
