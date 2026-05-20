<?php

namespace App\Services\Optical;

use App\Models\Device;
use App\Models\OnuSignalLog;
use App\Models\SignalAlert;
use App\Models\SupportTicket;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use App\Support\OnuSignalLevel;
use App\Support\OpticalThresholds;
use Carbon\Carbon;

final class OnuSignalAlertService
{
    public function evaluateOnu(Device $onu, Carbon $now): int
    {
        if ($onu->type !== 'onu') {
            return 0;
        }

        $created = 0;
        $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $tx = $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null;
        $oper = strtolower((string) ($onu->onu_oper_status ?? 'unknown'));
        $rxLevel = OnuSignalLevel::classifyRx($rx, $oper);
        $txLevel = OnuSignalLevel::classifyTx($tx);

        if (in_array($oper, ['los', 'offline'], true)) {
            $created += $this->openAlert($onu, SignalAlert::TYPE_LOS, 'critical', 'ONU offline / LOS', "ONU {$onu->serial_number} is {$oper}.", $rx, $tx, $now);
        }

        if ($rxLevel === OnuSignalLevel::HIGH && $rx !== null && config('optical.alert_on_high_rx', true)) {
            $created += $this->openAlert(
                $onu,
                SignalAlert::TYPE_HIGH_RX,
                'warning',
                'RX laser high',
                sprintf('RX %.2f dBm exceeds high threshold (%.1f dBm). Check patch cord / attenuator.', $rx, OpticalThresholds::rxHighWarnAbove()),
                $rx,
                $tx,
                $now,
            );
        } elseif ($rxLevel === OnuSignalLevel::CRITICAL && $rx !== null) {
            $created += $this->openAlert($onu, SignalAlert::TYPE_LOW_RX, 'critical', 'Critical RX power', "RX {$rx} dBm below threshold.", $rx, $tx, $now);
        } elseif ($rxLevel === OnuSignalLevel::WARNING && $rx !== null) {
            $created += $this->openAlert($onu, SignalAlert::TYPE_LOW_RX, 'warning', 'Weak RX signal', "RX {$rx} dBm is weak.", $rx, $tx, $now);
        }

        if ($txLevel === OnuSignalLevel::HIGH && $tx !== null && config('optical.alert_on_high_tx', true)) {
            $created += $this->openAlert(
                $onu,
                SignalAlert::TYPE_HIGH_TX,
                'warning',
                'TX laser high',
                sprintf('TX %.2f dBm exceeds high threshold (%.1f dBm).', $tx, OpticalThresholds::txHighWarnAbove()),
                $rx,
                $tx,
                $now,
            );
        } elseif ($txLevel === OnuSignalLevel::WARNING && $tx !== null) {
            $created += $this->openAlert($onu, SignalAlert::TYPE_TX_ABNORMAL, 'warning', 'Abnormal TX power', "TX {$tx} dBm outside normal range.", $rx, $tx, $now);
        }

        $prev = OnuSignalLog::query()
            ->where('device_id', $onu->id)
            ->where('granularity', 'snapshot')
            ->orderByDesc('sampled_at')
            ->skip(1)
            ->first();

        if ($prev && $rx !== null && $prev->rx_power_dbm !== null) {
            $drop = (float) $prev->rx_power_dbm - $rx;
            if ($drop >= (float) config('optical.sudden_drop_db', 3)) {
                $created += $this->openAlert($onu, SignalAlert::TYPE_SUDDEN_DROP, 'warning', 'Sudden signal drop', "RX dropped {$drop} dB.", $rx, $tx, $now);
            }
        }

        if (in_array($rxLevel, [OnuSignalLevel::GOOD, OnuSignalLevel::EXCELLENT], true)) {
            $this->resolveOpenAlerts($onu, [SignalAlert::TYPE_LOW_RX, SignalAlert::TYPE_SUDDEN_DROP, SignalAlert::TYPE_HIGH_RX]);
        }

        if ($txLevel === OnuSignalLevel::GOOD) {
            $this->resolveOpenAlerts($onu, [SignalAlert::TYPE_TX_ABNORMAL, SignalAlert::TYPE_HIGH_TX]);
        }

        return $created;
    }

    private function openAlert(
        Device $onu,
        string $type,
        string $severity,
        string $title,
        string $message,
        ?float $rx,
        ?float $tx,
        Carbon $now,
    ): int {
        $exists = SignalAlert::query()
            ->where('device_id', $onu->id)
            ->where('alert_type', $type)
            ->where('status', 'open')
            ->exists();

        if ($exists) {
            return 0;
        }

        $ticketId = null;
        if ($severity === 'critical' && config('optical.auto_ticket_enabled', true)) {
            $ticketId = $this->createSupportTicket($onu, $title, $message);
        }

        SignalAlert::query()->create([
            'tenant_id' => $onu->tenant_id,
            'device_id' => $onu->id,
            'olt_id' => $onu->olt_id,
            'olt_port_id' => $onu->olt_port_id,
            'customer_id' => $onu->customer_id,
            'support_ticket_id' => $ticketId,
            'alert_type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'rx_power_dbm' => $rx,
            'tx_power_dbm' => $tx,
            'status' => 'open',
            'detected_at' => $now,
        ]);

        $this->notify($onu, $title, $message, $severity);

        return 1;
    }

    /**
     * @param  list<string>  $types
     */
    private function resolveOpenAlerts(Device $onu, array $types): void
    {
        SignalAlert::query()
            ->where('device_id', $onu->id)
            ->whereIn('alert_type', $types)
            ->where('status', 'open')
            ->update(['status' => 'resolved', 'resolved_at' => now()]);
    }

    private function createSupportTicket(Device $onu, string $title, string $message): ?int
    {
        try {
            $ticket = SupportTicket::query()->create([
                'tenant_id' => $onu->tenant_id,
                'customer_id' => $onu->customer_id,
                'subject' => '[Optical] '.$title,
                'description' => $message."\n\nONU: ".$onu->serial_number,
                'priority' => 'high',
                'status' => 'open',
                'channel' => 'system',
            ]);

            return $ticket->id;
        } catch (\Throwable) {
            return null;
        }
    }

    private function notify(Device $onu, string $title, string $message, string $severity): void
    {
        if (config('optical.notify_ops', true)) {
            try {
                app(NotificationDispatcher::class)->notifyOps(
                    (int) $onu->tenant_id,
                    NotificationEvent::OUTAGE,
                    OpticalOpsAlertFormatter::variablesForOnu($onu, $message),
                );
            } catch (\Throwable) {
                //
            }
        }

        if (config('optical.notify_customer_weak_signal', false) && $onu->customer_id) {
            $customer = $onu->customer;
            if ($customer) {
                try {
                    app(NotificationDispatcher::class)->notifyCustomer(
                        $customer,
                        NotificationEvent::OUTAGE,
                        ['title' => $title, 'message' => $message],
                    );
                } catch (\Throwable) {
                    //
                }
            }
        }
    }
}
