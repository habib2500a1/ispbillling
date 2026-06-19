<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;
use App\Models\SupportTicket;
use App\Services\Network\MikrotikNetworkProvisioner;
use Illuminate\Support\Collection;

final class OnuBulkOperationsService
{
    /**
     * @return array{processed: int, notes: list<string>}
     */
    public function reboot(Collection $onus): array
    {
        $notes = [];
        $processed = 0;

        foreach ($onus as $onu) {
            if (! $onu instanceof Device || $onu->type !== 'onu') {
                continue;
            }

            if ($onu->customer_id) {
                SupportTicket::query()->create([
                    'tenant_id' => $onu->tenant_id,
                    'customer_id' => $onu->customer_id,
                    'subject' => 'ONU reboot requested (bulk)',
                    'description' => 'Bulk reboot from OLT ONU table. Serial: '.($onu->serial_number ?? 'n/a'),
                    'issue_type' => 'equipment',
                    'priority' => 'medium',
                    'status' => 'open',
                    'channel' => 'system',
                ]);
            }

            $notes[] = 'Reboot queued via ticket for ONU #'.$onu->id;
            $processed++;
        }

        return ['processed' => $processed, 'notes' => $notes];
    }

    /**
     * @return array{processed: int, linked: int}
     */
    public function authorize(Collection $onus, ?int $customerId = null): array
    {
        $processed = 0;
        $linked = 0;

        foreach ($onus as $onu) {
            if (! $onu instanceof Device) {
                continue;
            }

            $onu->forceFill([
                'onu_oper_status' => 'online',
                'status' => 'assigned',
            ]);

            if ($customerId && ! $onu->customer_id) {
                $onu->customer_id = $customerId;
                $linked++;
            }

            $onu->save();
            $processed++;
        }

        return compact('processed', 'linked');
    }

    /**
     * @return array{processed: int, suspended: int}
     */
    public function disable(Collection $onus): array
    {
        $processed = 0;
        $suspended = 0;
        $provisioner = app(MikrotikNetworkProvisioner::class);

        foreach ($onus as $onu) {
            if (! $onu instanceof Device || ! $onu->customer_id) {
                continue;
            }

            $customer = Customer::query()->find($onu->customer_id);
            if ($customer) {
                $customer->forceFill(['network_access_state' => 'suspended'])->saveQuietly();
                $provisioner->suspendCustomer($customer, 'Bulk ONU disable from OLT table');
                $suspended++;
            }

            $processed++;
        }

        return compact('processed', 'suspended');
    }

    /**
     * @return array{processed: int}
     */
    public function movePon(Collection $onus, int $cardNo, int $ponNo): array
    {
        $processed = 0;

        foreach ($onus as $onu) {
            if (! $onu instanceof Device) {
                continue;
            }

            $onu->forceFill(['card_no' => $cardNo, 'pon_no' => $ponNo])->save();
            $processed++;
        }

        return ['processed' => $processed];
    }
}
