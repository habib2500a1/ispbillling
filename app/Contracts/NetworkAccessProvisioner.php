<?php

namespace App\Contracts;

use App\Models\Customer;

interface NetworkAccessProvisioner
{
    /**
     * Block PPPoE / RADIUS / CPE access for non-payment or policy breach.
     */
    public function suspendCustomer(Customer $customer, string $reason): void;

    /**
     * Restore access after payment or manual clearance.
     */
    public function unsuspendCustomer(Customer $customer): void;

    /**
     * Re-apply bandwidth / FUP / VLAN policy (future: MikroTik queues, RADIUS attributes).
     */
    public function syncAccessPolicy(Customer $customer): void;

    /**
     * Placeholder for ONU reboot / profile push (vendor-specific drivers).
     */
    public function pushOnuRuntimeState(\App\Models\Device $onu): void;
}
