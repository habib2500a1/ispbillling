<?php

namespace App\Services\Network;

use App\Contracts\NetworkAccessProvisioner;
use App\Models\Customer;
use App\Models\Device;
use Illuminate\Support\Facades\Log;

final class NullNetworkProvisioner implements NetworkAccessProvisioner
{
    public function suspendCustomer(Customer $customer, string $reason): void {}

    public function unsuspendCustomer(Customer $customer): void {}

    public function syncAccessPolicy(Customer $customer): void {}

    public function pushOnuRuntimeState(Device $onu): void {}
}
