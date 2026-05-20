<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\StaffCustomerFormService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCustomerStoreController extends Controller
{
    public function formOptions(Request $request, StaffCustomerFormService $forms): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCreate($user);

        return response()->json($forms->formOptions($user));
    }

    public function store(Request $request, StaffCustomerFormService $forms): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $this->authorizeCreate($user);

        $result = $forms->create($user, $request);
        $customer = $result['customer'];

        return response()->json([
            'message' => 'Customer created.',
            'customer' => [
                'id' => $customer->id,
                'customer_code' => $customer->customer_code,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'status' => $customer->status,
                'network_access_state' => $customer->network_access_state,
                'mikrotik_secret_name' => $customer->mikrotik_secret_name,
                'package' => $customer->package?->name,
            ],
            'network' => $result['network'],
            'billing' => $result['billing'] ?? ['invoice' => null, 'message' => ''],
        ], 201);
    }

    /** @deprecated Use formOptions */
    public function packages(Request $request, StaffCustomerFormService $forms): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $forms->formOptions($user)['packages']]);
    }

    private function authorizeCreate(User $user): void
    {
        abort_unless(
            $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager', 'cashier', 'collector']),
            403,
            'Not allowed to create customers.',
        );
    }
}
