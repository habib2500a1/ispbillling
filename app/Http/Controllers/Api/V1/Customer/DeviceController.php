<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Mobile\DeviceTokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function register(Request $request, DeviceTokenService $tokens): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
            'platform' => ['required', 'string', 'in:android,ios,web'],
        ]);

        $tokens->register($customer, 'customer', $data['platform'], $data['token']);

        return response()->json(['message' => 'Device registered for push notifications.']);
    }

    public function unregister(Request $request, DeviceTokenService $tokens): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $data = $request->validate([
            'token' => ['required', 'string', 'max:512'],
        ]);

        $tokens->unregister($customer, $data['token']);

        return response()->json(['message' => 'Device unregistered.']);
    }
}
