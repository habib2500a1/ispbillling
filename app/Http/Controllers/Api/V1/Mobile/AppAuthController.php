<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Api\V1\Customer\AuthController as CustomerAuthController;
use App\Http\Controllers\Api\V1\Staff\AuthController as StaffAuthController;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Mobile\CustomerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Unified login for native Android app (Kotlin).
 * POST /api/v1/login — returns user_type: client | admin
 */
class AppAuthController extends Controller
{
    public function login(Request $request, CustomerMobileService $mobile): JsonResponse
    {
        $data = $request->validate([
            'email' => ['nullable', 'string', 'max:255'],
            'username' => ['nullable', 'string', 'max:255'],
            'login' => ['nullable', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $identifier = trim((string) ($data['login'] ?? $data['email'] ?? $data['username'] ?? ''));
        if ($identifier === '') {
            return response()->json(['message' => 'Email, username, or phone is required.'], 422);
        }

        if (str_contains($identifier, '@')) {
            return $this->staffLogin($request, $identifier, $data['password'], $data['device_name'] ?? 'isp-android');
        }

        $customer = Customer::findForPortalLogin($identifier);
        if ($customer !== null && Hash::check($data['password'], (string) $customer->portal_password)) {
            $request->merge([
                'login' => $identifier,
                'password' => $data['password'],
                'device_name' => $data['device_name'] ?? 'isp-android',
            ]);

            $response = app(CustomerAuthController::class)->login($request, $mobile);
            $payload = $response->getData(true);
            if (! is_array($payload)) {
                return $response;
            }

            return response()->json([
                'token' => $payload['token'] ?? null,
                'token_type' => $payload['token_type'] ?? 'Bearer',
                'expires_at' => $payload['expires_at'] ?? null,
                'user_type' => 'client',
                'user' => $payload['customer'] ?? null,
            ], $response->getStatusCode());
        }

        return response()->json(['message' => 'Invalid credentials.'], 401);
    }

    private function staffLogin(Request $request, string $email, string $password, string $deviceName): JsonResponse
    {
        $user = User::withoutGlobalScopes()->where('email', $email)->first();
        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $request->merge([
            'email' => $email,
            'password' => $password,
            'device_name' => $deviceName,
        ]);

        $response = app(StaffAuthController::class)->login($request);
        $payload = $response->getData(true);
        if (! is_array($payload)) {
            return $response;
        }

        return response()->json([
            'token' => $payload['token'] ?? null,
            'token_type' => $payload['token_type'] ?? 'Bearer',
            'expires_at' => $payload['expires_at'] ?? null,
            'user_type' => 'admin',
            'user' => $payload['user'] ?? null,
        ], $response->getStatusCode());
    }
}
