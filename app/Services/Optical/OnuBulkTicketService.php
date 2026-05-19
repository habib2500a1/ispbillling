<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\SupportTicket;
use App\Support\OnuSignalLevel;

final class OnuBulkTicketService
{
    /**
     * @param  \Illuminate\Support\Collection<int, Device>  $onus
     */
    public function createTicketsForWeakOnus($onus): int
    {
        $created = 0;

        foreach ($onus as $onu) {
            if ($onu->customer_id === null) {
                continue;
            }

            $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
            $level = OnuSignalLevel::classifyRx($rx, strtolower((string) ($onu->onu_oper_status ?? 'unknown')));
            if (! in_array($level, [OnuSignalLevel::WARNING, OnuSignalLevel::CRITICAL], true)) {
                continue;
            }

            $exists = SupportTicket::query()
                ->where('customer_id', $onu->customer_id)
                ->where('subject', 'like', '%[Optical]%')
                ->whereNotIn('status', ['resolved', 'closed'])
                ->where('created_at', '>=', now()->subDays(3))
                ->exists();

            if ($exists) {
                continue;
            }

            SupportTicket::query()->create([
                'tenant_id' => $onu->tenant_id,
                'customer_id' => $onu->customer_id,
                'channel' => 'system',
                'department' => 'technical_support',
                'priority' => $level === OnuSignalLevel::CRITICAL ? 'high' : 'medium',
                'subject' => '[Optical] Weak ONU signal — '.$onu->serial_number,
                'description' => sprintf(
                    "ONU %s RX: %s dBm\nStatus: %s",
                    $onu->serial_number,
                    $rx ?? 'n/a',
                    $onu->onu_oper_status ?? 'unknown',
                ),
                'status' => 'open',
            ]);

            $created++;
        }

        return $created;
    }
}
