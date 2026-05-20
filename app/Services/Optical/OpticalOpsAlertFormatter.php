<?php

namespace App\Services\Optical;

use App\Models\Customer;
use App\Models\Device;

/**
 * Builds Telegram ops variables for optical signal alerts (username, customer id, DB RX/TX).
 */
final class OpticalOpsAlertFormatter
{
    /**
     * @return array{count: int, message: string, customer_list: string}
     */
    public static function variablesForOnu(Device $onu, string $alertMessage): array
    {
        $onu->loadMissing([
            'customer:id,tenant_id,name,customer_code,mikrotik_secret_name,radius_username',
            'olt:id,display_name,serial_number',
        ]);

        return [
            'count' => 1,
            'message' => $alertMessage,
            'customer_list' => self::formatCustomerBlock($onu),
        ];
    }

    private static function formatCustomerBlock(Device $onu): string
    {
        $lines = [];
        $customer = $onu->customer;

        if ($customer instanceof Customer) {
            $lines[] = sprintf(
                '• %s | Code: %s | User: %s | Cust ID: %d',
                $customer->name,
                $customer->customer_code ?? '—',
                $customer->pppLoginName(),
                $customer->id,
            );
        } else {
            $lines[] = '• Unlinked ONU (device #'.$onu->id.')';
        }

        $rx = $onu->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 2).' dBm' : '—';
        $tx = $onu->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 2).' dBm' : '—';
        $port = self::onuPortLabel($onu);
        $mac = $onu->mac_address ?: '—';
        $status = (string) ($onu->onu_oper_status ?? '—');
        $olt = $onu->olt?->display_name ?? $onu->olt?->serial_number;

        $lines[] = sprintf(
            '  ONU %s | MAC %s | DB RX %s / TX %s | %s',
            $port,
            $mac,
            $rx,
            $tx,
            $status,
        );

        if (filled($olt)) {
            $lines[] = '  OLT: '.$olt;
        }

        if (filled($onu->last_polled_at)) {
            $lines[] = '  Polled: '.$onu->last_polled_at->format('d-M-Y H:i:s');
        }

        return implode("\n", $lines);
    }

    private static function onuPortLabel(Device $onu): string
    {
        if (filled($onu->display_name)) {
            return (string) $onu->display_name;
        }

        $parts = array_filter([
            $onu->card_no !== null ? 'C'.$onu->card_no : null,
            $onu->pon_no !== null ? 'P'.$onu->pon_no : null,
            $onu->onu_index !== null ? ':'.$onu->onu_index : null,
        ]);

        if ($parts !== []) {
            return implode('', $parts);
        }

        return $onu->serial_number ?: 'ONU #'.$onu->id;
    }
}
