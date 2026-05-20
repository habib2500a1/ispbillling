<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use App\Services\Mobile\MobileBroadcastService;
use App\Services\Network\MikrotikNetworkProvisioner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NetworkController extends Controller
{
    public function suspend(Request $request, MikrotikNetworkProvisioner $network, MobileBroadcastService $broadcast): JsonResponse
    {
        $this->authorizeStaff($request);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $customer = Customer::query()->findOrFail((int) $data['customer_id']);
        $customer->forceFill(['network_access_state' => 'suspended'])->save();
        $network->suspendCustomer($customer, $data['reason'] ?? 'mobile-api');

        $broadcast->routerAlert(
            (int) $customer->tenant_id,
            "Customer {$customer->customer_code} suspended",
            ['customer_id' => $customer->id],
        );

        return response()->json([
            'message' => 'Customer suspended.',
            'customer_id' => $customer->id,
            'status' => $customer->network_access_state,
        ]);
    }

    public function reconnect(Request $request, MikrotikNetworkProvisioner $network, MobileBroadcastService $broadcast): JsonResponse
    {
        $this->authorizeStaff($request);

        $data = $request->validate([
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
        ]);

        $customer = Customer::query()->findOrFail((int) $data['customer_id']);
        $customer->forceFill(['network_access_state' => 'active'])->save();
        $network->unsuspendCustomer($customer);
        $network->syncAccessPolicy($customer);

        $broadcast->routerAlert(
            (int) $customer->tenant_id,
            "Customer {$customer->customer_code} reconnected",
            ['customer_id' => $customer->id],
        );

        return response()->json([
            'message' => 'Customer reconnected.',
            'customer_id' => $customer->id,
            'status' => $customer->network_access_state,
        ]);
    }

    private function authorizeStaff(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        if (! $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager', 'isp-engineer', 'isp-support'])) {
            abort(403, 'Network control not allowed for this role.');
        }

        return $user;
    }
}
