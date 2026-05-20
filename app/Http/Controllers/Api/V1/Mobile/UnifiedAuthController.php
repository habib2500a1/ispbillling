<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Controller;
use App\Services\Mobile\CustomerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Single login endpoint for the unified mobile app (staff vs customer).
 */
class UnifiedAuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'role' => ['required', 'string', 'in:staff,customer'],
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['role'] === 'customer') {
            $request->merge([
                'login' => $data['login'],
                'password' => $data['password'],
                'device_name' => $data['device_name'] ?? 'isp-radiant-app',
            ]);

            return app(CustomerAuthController::class)->login($request, app(CustomerMobileService::class));
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

        return app(StaffAuthController::class)->login($request);
    }
}
