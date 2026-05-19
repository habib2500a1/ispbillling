<?php

namespace App\Services\Portal;

use App\Models\Customer;
use App\Models\Device;
use App\Support\OnuSignalLevel;

final class CustomerOnuOpticalService
{
    /**
     * @return array<string, mixed>
     */
    public function snapshot(Customer $customer): array
    {
        $onu = $customer->primaryOnu();
        if ($onu === null) {
            $onu = Device::query()
                ->withoutGlobalScopes()
                ->where('type', 'onu')
                ->where('customer_id', $customer->id)
                ->with(['onuHealthScore:id,device_id,smoothed_rx_dbm,smoothed_tx_dbm,stability_score,fiber_health_score,health_score,root_cause_hint'])
                ->first([
                    'id', 'customer_id', 'display_name', 'serial_number', 'mac_address', 'meta',
                    'onu_oper_status', 'rx_power_dbm', 'tx_power_dbm', 'last_polled_at',
                ]);
        } else {
            $onu->loadMissing('onuHealthScore');
        }

        if ($onu === null) {
            return [
                'linked' => false,
                'hint' => 'ONU এখনও আপনার অ্যাকাউন্টের সাথে যুক্ত নয়। সাপোর্টে EPON পোর্ট জানান।',
            ];
        }

        $rx = $onu->onuHealthScore?->smoothed_rx_dbm ?? $onu->rx_power_dbm;
        $tx = $onu->onuHealthScore?->smoothed_tx_dbm ?? $onu->tx_power_dbm;
        $oper = strtolower((string) ($onu->onu_oper_status ?? 'unknown'));
        $level = OnuSignalLevel::classifyRx($rx !== null ? (float) $rx : null, $oper);
        $health = $onu->onuHealthScore;

        return [
            'linked' => true,
            'device_id' => $onu->id,
            'label' => $onu->display_name ?: $onu->serial_number,
            'mac' => $onu->mac_address,
            'serial' => $onu->serial_number,
            'model' => is_array($onu->meta) ? ($onu->meta['model'] ?? $onu->meta['bdcom_label'] ?? null) : null,
            'oper_status' => $oper,
            'rx_dbm' => $rx !== null ? round((float) $rx, 2) : null,
            'tx_dbm' => $tx !== null ? round((float) $tx, 2) : null,
            'rx_level' => $level,
            'rx_level_label' => OnuSignalLevel::labels()[$level] ?? $level,
            'color' => OnuSignalLevel::filamentColor($level),
            'stability_percent' => (int) ($health?->stability_score ?? 0),
            'fiber_health_percent' => (int) ($health?->fiber_health_score ?? $health?->health_score ?? 0),
            'root_cause' => $health?->root_cause_hint,
            'last_polled' => $onu->last_polled_at?->diffForHumans(),
            'last_polled_at' => $onu->last_polled_at?->toIso8601String(),
        ];
    }
}
