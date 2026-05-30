<?php

namespace App\Services\Resellers;

use App\Jobs\SyncCustomerNetworkAccessJob;
use App\Models\Customer;
use App\Models\Reseller;
use App\Services\Network\MikrotikNetworkProvisioner;
use App\Services\Network\NetworkAccessCoordinator;
use App\Services\Subscribers\CustomerServiceRenewalService;
use App\Support\CustomerNetworkSync;
use App\Support\CustomerStatus;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class ResellerCustomerLifecycleService
{
    public function __construct(
        private readonly ResellerCustomerService $customers,
        private readonly CustomerServiceRenewalService $renewal,
        private readonly MikrotikNetworkProvisioner $network,
        private readonly NetworkAccessCoordinator $networkAccess,
        private readonly ResellerPortalActivityLogger $activity,
    ) {}

    public function renew(Reseller $reseller, Customer $customer, Request $request): array
    {
        $this->customers->assertOwned($reseller, $customer);
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_EDIT)) {
            throw ValidationException::withMessages(['permission' => 'Renew is not allowed.']);
        }

        $data = $request->validate([
            'days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        $days = (int) ($data['days'] ?? 30);
        $result = $this->renewal->extendDays($customer, $days);

        $this->activity->log($reseller, 'customer.renew', $customer, ['days' => $days], $request);

        return [
            'message' => "Service renewed for {$days} days until {$result['expires_at']}.",
            'expires_at' => $result['expires_at'],
        ];
    }

    public function suspend(Reseller $reseller, Customer $customer, Request $request): array
    {
        $this->customers->assertOwned($reseller, $customer);
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_SUSPEND)) {
            throw ValidationException::withMessages(['permission' => 'Suspend is not allowed.']);
        }

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $customer->forceFill([
            'status' => CustomerStatus::SUSPENDED,
            'network_access_state' => 'suspended',
        ])->save();

        $this->network->suspendCustomer($customer, $data['reason'] ?? 'reseller-portal');
        $this->activity->log($reseller, 'customer.suspend', $customer, ['reason' => $data['reason'] ?? null], $request);

        return ['message' => 'Subscriber suspended and network disconnected.'];
    }

    public function reconnect(Reseller $reseller, Customer $customer, Request $request): array
    {
        $this->customers->assertOwned($reseller, $customer);
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_SUSPEND)) {
            throw ValidationException::withMessages(['permission' => 'Reconnect is not allowed.']);
        }

        $customer->forceFill([
            'status' => CustomerStatus::ACTIVE,
            'network_access_state' => 'active',
        ])->save();

        $this->network->unsuspendCustomer($customer);
        $this->network->syncAccessPolicy($customer);
        SyncCustomerNetworkAccessJob::dispatch((int) $customer->tenant_id, (int) $customer->id)->afterResponse();

        $this->activity->log($reseller, 'customer.reconnect', $customer, [], $request);

        return ['message' => 'Subscriber reconnected and network enabled.'];
    }

    public function changePassword(Reseller $reseller, Customer $customer, Request $request): array
    {
        $this->customers->assertOwned($reseller, $customer);
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_EDIT)) {
            throw ValidationException::withMessages(['permission' => 'Password change is not allowed.']);
        }

        $data = $request->validate([
            'password' => ['required', 'string', 'min:4', 'max:64'],
        ]);

        $customer->forceFill(['mikrotik_ppp_password' => $data['password']])->save();
        CustomerNetworkSync::forceNetOn($customer->fresh() ?? $customer);

        $this->activity->log($reseller, 'customer.password_change', $customer, [], $request);

        return ['message' => 'PPPoE password updated and synced to network.'];
    }

    public function disconnectSession(Reseller $reseller, Customer $customer, Request $request): array
    {
        $this->customers->assertOwned($reseller, $customer);
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::NETWORK_VIEW)) {
            throw ValidationException::withMessages(['permission' => 'Network action is not allowed.']);
        }

        $this->network->suspendCustomer($customer, 'reseller-kick');
        $customer->forceFill(['is_ppp_online' => false])->saveQuietly();

        $this->activity->log($reseller, 'network.disconnect', $customer, [], $request);

        return ['message' => 'Active PPPoE session disconnected.'];
    }
}
