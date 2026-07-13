<?php

namespace App\Services\Olt;

use App\Models\CustomerOnu;
use App\Models\CustomerOnuRxHistory;
use Carbon\Carbon;

final class OpticalRxHistoryService
{
    public function record(CustomerOnu $onu, ?string $source = null): void
    {
        if ($onu->rx_power_dbm === null && $onu->tx_power_dbm === null) {
            return;
        }

        $latest = CustomerOnuRxHistory::query()
            ->where('customer_onu_id', $onu->id)
            ->orderByDesc('recorded_at')
            ->first();

        if ($latest
            && $latest->rx_power_dbm == $onu->rx_power_dbm
            && $latest->tx_power_dbm == $onu->tx_power_dbm
            && $latest->recorded_at?->gt(now()->subMinutes(15))) {
            return;
        }

        CustomerOnuRxHistory::create([
            'customer_onu_id' => $onu->id,
            'rx_power_dbm' => $onu->rx_power_dbm,
            'tx_power_dbm' => $onu->tx_power_dbm,
            'source' => $source ?? $onu->source ?? 'manual',
            'recorded_at' => $onu->last_polled_at ?? now(),
        ]);

        // Keep last 90 days per ONU
        $cutoff = Carbon::now()->subDays(90);
        CustomerOnuRxHistory::query()
            ->where('customer_onu_id', $onu->id)
            ->where('recorded_at', '<', $cutoff)
            ->delete();
    }

    /**
     * @return list<array{rx: ?string, tx: ?string, at: string}>
     */
    public function recentForOnu(CustomerOnu $onu, int $limit = 12): array
    {
        return CustomerOnuRxHistory::query()
            ->where('customer_onu_id', $onu->id)
            ->orderByDesc('recorded_at')
            ->limit($limit)
            ->get()
            ->map(fn (CustomerOnuRxHistory $h) => [
                'rx' => $h->rx_power_dbm !== null ? number_format((float) $h->rx_power_dbm, 2) : null,
                'tx' => $h->tx_power_dbm !== null ? number_format((float) $h->tx_power_dbm, 2) : null,
                'at' => optional($h->recorded_at)->format('d M H:i') ?? '—',
            ])
            ->all();
    }
}
