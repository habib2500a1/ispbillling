<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(6)],
        ]);

        if (! Hash::check($data['current_password'], (string) $customer->portal_password)) {
            return response()->json(['message' => 'Current password is incorrect.'], 422);
        }

        $customer->forceFill([
            'portal_password' => Hash::make($data['password']),
        ])->save();

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
