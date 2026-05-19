<?php

namespace App\Services\Network;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

final class LogNetworkProvisioner implements NetworkAccessProvisioner
{
    public function suspendCustomer(Customer $customer, string $reason): void
    {
        Log::channel('single')->info('network.suspend', [
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
            'reason' => $reason,
        ]);
    }

    public function unsuspendCustomer(Customer $customer): void
    {
        Log::channel('single')->info('network.unsuspend', [
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
        ]);
    }

    public function syncAccessPolicy(Customer $customer): void
    {
        Log::channel('single')->info('network.sync_policy', [
            'customer_id' => $customer->id,
            'tenant_id' => $customer->tenant_id,
        ]);
    }

    public function pushOnuRuntimeState(Device $onu): void
    {
        Log::channel('single')->info('network.onu_push', [
            'device_id' => $onu->id,
            'tenant_id' => $onu->tenant_id,
            'serial' => $onu->serial_number,
        ]);
    }
}
