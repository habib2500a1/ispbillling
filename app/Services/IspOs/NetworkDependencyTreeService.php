<?php

namespace App\Services\IspOs;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Network\FiberPlantMapService;
use App\Support\OnuSignalLevel;

/**
 * Customer → Internet dependency chain (read-only).
 */
final class NetworkDependencyTreeService
{
    public function __construct(
        private readonly FiberPlantMapService $fiberMap,
    ) {}

    /**
     * @return array{found: bool, chain: list<array{label: string, status: string, detail?: string, url?: string}>}
     */
    public function forCustomer(int $customerId): array
    {
        $customer = Customer::query()
            ->with(['zone:id,name', 'mikrotikServer:id,name', 'onuDevice:id,customer_id,olt_id,serial_number,mac_address,rx_power_dbm,onu_oper_status,display_name,card_no,pon_no'])
            ->find($customerId);

        if ($customer === null) {
            return ['found' => false, 'chain' => []];
        }

        $chain = [];
        $chain[] = [
            'label' => 'Customer',
            'status' => $customer->is_ppp_online ? 'online' : 'offline',
            'detail' => $customer->name.' · '.$customer->customer_code,
            'url' => \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $customer->id]),
        ];

        $onu = $customer->onuDevice;
        if ($onu) {
            $onuStatus = in_array(strtolower((string) $onu->onu_oper_status), ['online', 'active', 'up'], true) ? 'online' : 'offline';
            $signal = OnuSignalLevel::classifyRx(
                $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null,
                strtolower((string) ($onu->onu_oper_status ?? '')),
            );
            $chain[] = [
                'label' => 'ONU',
                'status' => $onuStatus === 'online' ? ($signal === 'critical' || $signal === 'warning' ? 'warning' : 'online') : 'offline',
                'detail' => ($onu->serial_number ?: $onu->mac_address ?: $onu->display_name).' · RX '.($onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 1).' dBm' : '—'),
            ];
        } else {
            $chain[] = ['label' => 'ONU', 'status' => 'unknown', 'detail' => 'Not assigned'];
        }

        $trace = $this->fiberMap->traceForCustomerId($customerId);
        if ($trace['found'] ?? false) {
            $chain[] = ['label' => 'Splitter', 'status' => 'online', 'detail' => count($trace['nodes'] ?? []).' plant nodes'];
            $chain[] = ['label' => 'Fiber route', 'status' => 'online', 'detail' => number_format((float) ($trace['total_length_m'] ?? 0), 0).' m'];
        } else {
            $chain[] = ['label' => 'Splitter', 'status' => 'unknown', 'detail' => 'No GIS trace'];
        }

        if ($onu?->olt_id) {
            $olt = Device::query()->find($onu->olt_id, ['id', 'display_name', 'serial_number', 'status', 'management_ip', 'type']);
            if ($olt) {
                $pon = trim(($onu->card_no !== null ? 'C'.$onu->card_no.'/' : '').($onu->pon_no !== null ? 'P'.$onu->pon_no : ''), '/') ?: '—';
                $chain[] = [
                    'label' => 'PON',
                    'status' => 'online',
                    'detail' => $pon,
                ];
                $chain[] = [
                    'label' => 'OLT',
                    'status' => ($olt->status ?? '') === 'active' ? 'online' : 'offline',
                    'detail' => $olt->adminLabel().' · '.($olt->management_ip ?? '—'),
                    'url' => \App\Filament\Resources\OltResource::getUrl('edit', ['record' => $olt->id]),
                ];
            }
        }

        if ($customer->mikrotikServer) {
            $chain[] = [
                'label' => 'Router',
                'status' => ($customer->mikrotikServer->last_api_status ?? '') === 'online' ? 'online' : 'offline',
                'detail' => $customer->mikrotikServer->name,
                'url' => \App\Filament\Resources\MikrotikServerResource::getUrl('edit', ['record' => $customer->mikrotikServer->id]),
            ];
        }

        $chain[] = [
            'label' => 'Internet',
            'status' => $customer->is_ppp_online ? 'online' : 'offline',
            'detail' => $customer->is_ppp_online ? 'PPP session active' : 'No active session',
        ];

        return ['found' => true, 'chain' => $chain];
    }
}
