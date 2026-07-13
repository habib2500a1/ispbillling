<?php

namespace App\Services\Olt;

use App\Models\CustomerOnu;
use App\Models\CustomersInfo;

final class CustomerOpticalPresenter
{
    public function __construct(
        private readonly IspbillingOpticalBridge $bridge,
        private readonly OpticalRxHistoryService $history,
    ) {}

    /**
     * @return array{
     *   linked: bool,
     *   hint: ?string,
     *   row: ?array<string, mixed>,
     *   details: array<string, mixed>,
     * }
     */
    public function forCustomer(CustomersInfo $customer, bool $tryRemote = true): array
    {
        $onu = $customer->primaryOnu();

        if ($onu === null && $tryRemote) {
            $synced = $this->bridge->autoLinkCustomer($customer);
            if ($synced !== null) {
                $onu = $synced;
            }
        }

        if ($onu === null) {
            return [
                'linked' => false,
                'hint' => __('ONU not linked. Sync from OLT or enter optical details manually.'),
                'row' => null,
                'details' => [],
                'history' => [],
            ];
        }

        $rx = $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2) : null;
        $tx = $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 2) : null;

        return [
            'linked' => true,
            'hint' => null,
            'row' => [
                'optical_power' => $rx,
                'tx_power' => $tx,
                'olt_name' => $onu->olt_name ?: '—',
                'olt_port' => $onu->pon_port ?: '—',
            ],
            'details' => [
                'mac' => $onu->mac_address,
                'serial' => $onu->serial_number,
                'status' => $onu->oper_status,
                'source' => $onu->source,
                'last_polled_at' => optional($onu->last_polled_at)?->diffForHumans(),
            ],
            'history' => $this->history->recentForOnu($onu),
        ];
    }

    public function saveManual(CustomersInfo $customer, array $data): CustomerOnu
    {
        $onu = $customer->primaryOnu() ?? new CustomerOnu(['customers_info_id' => $customer->id]);

        $onu->fill([
            'customers_info_id' => $customer->id,
            'olt_id' => \App\Models\Olt::resolveIdByName($data['olt_name'] ?? $onu->olt_name),
            'olt_name' => $data['olt_name'] ?? $onu->olt_name,
            'pon_port' => $data['pon_port'] ?? $onu->pon_port,
            'mac_address' => $data['mac_address'] ?? $onu->mac_address,
            'serial_number' => $data['serial_number'] ?? $onu->serial_number,
            'rx_power_dbm' => $data['rx_power_dbm'] ?? $onu->rx_power_dbm,
            'tx_power_dbm' => $data['tx_power_dbm'] ?? $onu->tx_power_dbm,
            'oper_status' => $data['oper_status'] ?? $onu->oper_status,
            'source' => 'manual',
            'last_polled_at' => now(),
        ]);
        $onu->save();
        $this->history->record($onu, 'manual');

        return $onu;
    }
}
