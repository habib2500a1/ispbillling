<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user = User::withoutGlobalScopes()->where('email', $data['email'])->first();
        if ($user === null || ! Hash::check($data['password'], (string) $user->password)) {
            return response()->json(['message' => 'Invalid credentials.'], 401);
        }

        if ($user->is_active === false) {
            return response()->json(['message' => 'Account deactivated.'], 403);
        }

        $collectorRoles = ['cashier', 'branch-manager', 'admin'];
        $technicianRoles = ['super-admin', 'isp-admin', 'isp-engineer', 'isp-support', 'isp-manager'];

        if (! $user->hasAnyRole(array_merge($technicianRoles, $collectorRoles))) {
            return response()->json(['message' => 'Mobile access not allowed for this account.'], 403);
        }

        $expiresAt = now()->addDays((int) config('mobile.staff_token_expiry_days', 30));
        $abilities = ['staff'];
        if ($user->hasAnyRole($technicianRoles)) {
            $abilities[] = 'technician';
        }
        if ($user->hasAnyRole(array_merge($collectorRoles, ['super-admin', 'isp-admin']))) {
            $abilities[] = 'collector';
        }

        $token = $user->createToken(
            $data['device_name'] ?? config('mobile.technician_token_name', 'technician-app'),
            $abilities,
            $expiresAt,
        );

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'tenant_id' => $user->tenant_id,
                'roles' => $user->getRoleNames()->values()->all(),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['message' => 'Logged out.']);
    }
}
