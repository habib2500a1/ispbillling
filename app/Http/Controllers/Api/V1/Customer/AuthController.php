<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Mobile\CustomerMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request, CustomerMobileService $mobile): JsonResponse
    {
        $data = $request->validate([
            'login' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = Customer::findForPortalLogin($data['login']);
        if ($customer === null || ! Hash::check($data['password'], (string) $customer->portal_password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        $customer->recordPortalLogin();

        $issuedAt = now();
        $expiresAt = now()->addDays((int) config('mobile.customer_token_expiry_days', 90));
        $abilities = ['customer'];
        $token = $customer->createToken(
            $data['device_name'] ?? config('mobile.customer_token_name', 'customer-app'),
            $abilities,
            $expiresAt,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'auth_mode' => 'sanctum',
            'guard' => 'customer',
            'issued_at' => $issuedAt->toIso8601String(),
            'expires_at' => $expiresAt->toIso8601String(),
            'abilities' => $abilities,
            'customer' => $mobile->customerPayload($customer->load('package')),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $customer = $request->user();
        if ($customer instanceof Customer) {
            $customer->recordPortalLogout();
        }

        $customer?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request, CustomerMobileService $mobile): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        return response()->json([
            'customer' => $mobile->customerPayload($customer->load('package')),
        ]);
    }
}
