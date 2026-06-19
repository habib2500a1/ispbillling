<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;

final class UnauthorizedOnuApprovalService
{
    /**
     * Approve unauthorized ONU and bind to customer.
     *
     * @return array{ok: bool, message: string}
     */
    public function approveAndBind(Device $onu, Customer $customer): array
    {
        if ($onu->type !== 'onu') {
            return ['ok' => false, 'message' => 'Not an ONU device'];
        }

        if ($onu->tenant_id !== $customer->tenant_id) {
            return ['ok' => false, 'message' => 'Tenant mismatch'];
        }

        $onu->forceFill([
            'customer_id' => $customer->id,
            'onu_oper_status' => 'online',
            'status' => 'assigned',
            'offline_reason' => null,
        ]);

        $meta = is_array($onu->meta) ? $onu->meta : [];
        unset($meta['onu_warning'], $meta['unauthorized_detected_at']);
        $meta['approved_at'] = now()->toIso8601String();
        $meta['approved_customer_id'] = $customer->id;
        $onu->meta = $meta;
        $onu->save();

        $customerMeta = is_array($customer->meta) ? $customer->meta : [];
        $customerMeta['onu_device_id'] = $onu->id;
        $customerMeta['onu_serial'] = $onu->serial_number;
        $customer->forceFill(['meta' => $customerMeta])->saveQuietly();

        return [
            'ok' => true,
            'message' => 'ONU '.$onu->serial_number.' bound to '.$customer->name,
        ];
    }
}
