<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffDeviceController extends Controller
{
    public function register(Request $request, DeviceTokenService $tokens): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios'],
            'app' => ['nullable', 'string', 'in:admin,collector,technician,noc,staff'],
        ]);

        $tokens->register($user, $data['app'] ?? 'staff', $data['platform'], $data['token']);

        return response()->json(['message' => 'Device registered for push notifications.']);
    }
}
