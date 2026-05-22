<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\User;
use App\Services\Mobile\MobileBroadcastService;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Services\Subscribers\CustomerServiceRenewalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffCustomerQuickActionsController extends Controller
{
    private const ACCESS_ROLES = [
        'super-admin', 'isp-admin', 'admin', 'cashier', 'collector', 'branch-manager', 'isp-manager',
        'isp-engineer', 'isp-support',
    ];

    public function extendService(
        Request $request,
        int $customer,
        CustomerServiceRenewalService $renewal,
    ): JsonResponse {
        $this->authorizeStaff($request);

        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);

        $model = Customer::query()->findOrFail($customer);
        $days = (int) ($data['days'] ?? 30);
        $result = $renewal->extendDays($model, $days);

        return response()->json([
            'message' => "Service extended by {$days} days.",
            'customer_id' => $model->id,
            'service_expires_at' => $result['expires_at'],
            'expire_day' => \App\Support\BillingDefaults::expireDayFromDate($result['expires_at']),
        ]);
    }

    public function toggleNetwork(
        Request $request,
        int $customer,
        MikrotikNetworkProvisioner $network,
        MobileBroadcastService $broadcast,
    ): JsonResponse {
        $this->authorizeStaff($request);

        $model = Customer::query()->findOrFail($customer);
        $suspend = ($model->network_access_state ?? 'active') !== 'suspended';

        if ($suspend) {
            $model->forceFill(['network_access_state' => 'suspended'])->save();
            $network->suspendCustomer($model, 'mobile-toggle');
            $message = 'Network suspended.';
        } else {
            $model->forceFill(['network_access_state' => 'active', 'status' => 'active'])->save();
            $network->unsuspendCustomer($model);
            $network->syncAccessPolicy($model);
            SyncCustomerNetworkAccessJob::dispatch((int) $model->tenant_id, (int) $model->id)->afterResponse();
            $message = 'Network active.';
        }

        $broadcast->routerAlert(
            (int) $model->tenant_id,
            "{$model->customer_code}: {$message}",
            ['customer_id' => $model->id],
        );

        return response()->json([
            'message' => $message,
            'customer_id' => $model->id,
            'network_access_state' => $model->fresh()?->network_access_state,
            'suspended' => $suspend,
        ]);
    }

    private function authorizeStaff(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        if (! $user->hasAnyRole(self::ACCESS_ROLES)) {
            abort(403, 'Access denied.');
        }

        return $user;
    }
}
