<?php

namespace App\Services\Optical;

use App\Filament\Resources\SupportTicketResource;
use App\Models\Customer;
use App\Models\Device;
use App\Models\SupportTicket;
use App\Support\OnuSignalLevel;
use App\Support\SubscriberNetworkLabels;
use Carbon\Carbon;

/**
 * Subscriber page ONU operations panel — status, optics, uptime, firmware (500K-scale friendly read path).
 */
final class SubscriberOnuOpsPresenter
{
    public function __construct(
        private readonly OnuOfflineHandlingService $offlineHandling,
        private readonly OnuMacArchiveService $macArchive,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function forCustomer(Customer $customer): array
    {
        $onu = $customer->primaryOnu();
        if ($onu === null) {
            $onu = $customer->onuDevice()->with('olt')->first();
        }

        if ($onu === null) {
            return [
                'linked' => false,
                'warning' => $this->offlineHandling->customerWarning($customer),
            ];
        }

        $onu->loadMissing(['olt', 'onuHealthScore']);
        $meta = is_array($onu->meta) ? $onu->meta : [];
        $oper = strtolower((string) ($onu->onu_oper_status ?? 'unknown'));
        $rx = $onu->rx_power_dbm !== null ? (float) $onu->rx_power_dbm : null;
        $tx = $onu->tx_power_dbm !== null ? (float) $onu->tx_power_dbm : null;
        $rxLevel = OnuSignalLevel::classifyRx($rx, $oper);
        $warning = $this->offlineHandling->customerWarning($customer);

        return [
            'linked' => true,
            'onu_id' => $onu->id,
            'status' => $this->formatStatus($oper),
            'status_tone' => $this->statusTone($oper),
            'rx_dbm' => $rx,
            'rx_label' => $rx !== null ? number_format($rx, 1).' dBm' : '—',
            'rx_level' => $rxLevel,
            'rx_level_label' => OnuSignalLevel::labels()[$rxLevel] ?? $rxLevel,
            'tx_dbm' => $tx,
            'tx_label' => $tx !== null ? number_format($tx, 1).' dBm' : '—',
            'last_seen' => $this->formatTimestamp($meta['onu_last_seen_at'] ?? $onu->last_polled_at?->toIso8601String()),
            'offline_since' => $this->formatTimestamp($meta['onu_offline_since'] ?? null),
            'uptime' => $this->formatUptime($meta['onu_online_since'] ?? null, $oper),
            'reboot_count' => $this->rebootCount($customer, $onu),
            'firmware' => $this->firmwareVersion($onu, $meta),
            'olt' => $onu->olt?->display_name ?? $onu->olt?->hostname ?? '—',
            'pon' => SubscriberNetworkLabels::ponPortLabel($onu, $customer),
            'mac' => $onu->mac_address ?: ($onu->serial_number ?: '—'),
            'temperature' => isset($meta['optical']['temperature_c'])
                ? number_format((float) $meta['optical']['temperature_c'], 1).' °C'
                : '—',
            'distance' => isset($meta['distance_m']) || isset($meta['bdcom_distance'])
                ? ((int) round((float) ($meta['distance_m'] ?? $meta['bdcom_distance']))).' m'
                : '—',
            'warning' => $warning,
            'mac_archive' => $this->macArchive->archivedMacs($onu),
            'ticket_suggest_url' => ($warning['suggest_ticket'] ?? false)
                ? SupportTicketResource::getUrl('create', [
                    'customer_id' => $customer->id,
                    'issue_type' => 'equipment',
                ])
                : null,
        ];
    }

    private function formatStatus(string $oper): string
    {
        return match ($oper) {
            'online', 'active', 'up', 'working' => 'Online',
            'offline', 'down' => 'Offline',
            'los' => 'LOS',
            'dying_gasp' => 'Dying Gasp',
            'power_fail', 'power_off' => 'Power Off',
            'unauthorized', 'auth_fail', 'illegal' => 'Unauthorized',
            default => ucfirst(str_replace('_', ' ', $oper)),
        };
    }

    private function statusTone(string $oper): string
    {
        if (in_array($oper, ['online', 'active', 'up', 'working'], true)) {
            return 'success';
        }
        if (in_array($oper, ['unauthorized', 'auth_fail', 'illegal', 'los', 'dying_gasp'], true)) {
            return 'danger';
        }

        return 'warning';
    }

    private function formatTimestamp(mixed $raw): string
    {
        if (! filled($raw)) {
            return '—';
        }

        try {
            return Carbon::parse((string) $raw)->format('d M Y g:i A');
        } catch (\Throwable) {
            return (string) $raw;
        }
    }

    private function formatUptime(?string $onlineSince, string $oper): string
    {
        if (! in_array($oper, ['online', 'active', 'up', 'working'], true)) {
            return '—';
        }

        if (! filled($onlineSince)) {
            return '—';
        }

        try {
            return Carbon::parse($onlineSince)->diffForHumans(null, true).' online';
        } catch (\Throwable) {
            return '—';
        }
    }

    private function rebootCount(Customer $customer, Device $onu): int
    {
        $meta = is_array($onu->meta) ? $onu->meta : [];
        if (isset($meta['reboot_count']) && is_numeric($meta['reboot_count'])) {
            return (int) $meta['reboot_count'];
        }

        $driver = SupportTicket::query()->getConnection()->getDriverName();
        $likeOp = $driver === 'pgsql' ? 'ilike' : 'like';

        return (int) SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->where(function ($q) use ($likeOp): void {
                $q->where('subject', $likeOp, '%reboot%')
                    ->orWhere('description', $likeOp, '%reboot%');
            })
            ->count();
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function firmwareVersion(Device $onu, array $meta): string
    {
        $fw = $meta['firmware'] ?? $meta['firmware_version'] ?? $meta['optical']['firmware'] ?? null;

        return filled($fw) ? (string) $fw : '—';
    }
}
