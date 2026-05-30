<?php

namespace App\Services\Resellers;

use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\PppSessionLog;
use App\Services\Portal\CustomerBandwidthService;
use App\Support\BandwidthDirection;
use Illuminate\Support\Collection;

final class ResellerNetworkSessionService
{
    public function __construct(
        private readonly CustomerBandwidthService $bandwidth,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summarize(Customer $customer, bool $refreshLive = false): array
    {
        if ($refreshLive) {
            return $this->fromLiveStats($customer);
        }

        $session = $customer->relationLoaded('activePppSession')
            ? $customer->activePppSession
            : $customer->activePppSession()->with('mikrotikServer:id,name')->first();

        if ($session instanceof PppSessionLog) {
            return $this->fromSession($customer, $session);
        }

        return $this->offlineSummary($customer);
    }

    /**
     * @param  iterable<int, Customer>|Collection<int, Customer>  $clients
     * @return array<int, array<string, mixed>>
     */
    public function summarizeMany(iterable $clients, bool $refreshLiveForOnline = false): array
    {
        $rows = [];
        foreach ($clients as $customer) {
            $rows[(int) $customer->id] = $this->summarize(
                $customer,
                $refreshLiveForOnline && (bool) $customer->is_ppp_online,
            );
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    public function liveDetail(Customer $customer): array
    {
        $live = $this->bandwidth->liveStats($customer);
        $monthly = $this->bandwidth->monthlyUsage($customer);

        return array_merge($this->fromLiveStatsArray($customer, $live), [
            'month_download' => BandwidthUsageDaily::formatBytes((int) $monthly['bytes_in']),
            'month_upload' => BandwidthUsageDaily::formatBytes((int) $monthly['bytes_out']),
            'month_download_bytes' => (int) $monthly['bytes_in'],
            'month_upload_bytes' => (int) $monthly['bytes_out'],
            'peak_download' => BandwidthDirection::formatBps($monthly['peak_in_bps']),
            'peak_upload' => BandwidthDirection::formatBps($monthly['peak_out_bps']),
            'chart' => $live['chart'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fromLiveStats(Customer $customer): array
    {
        return $this->fromLiveStatsArray($customer, $this->bandwidth->liveStats($customer));
    }

    /**
     * @param  array<string, mixed>  $live
     * @return array<string, mixed>
     */
    private function fromLiveStatsArray(Customer $customer, array $live): array
    {
        $session = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->with('mikrotikServer:id,name')
            ->orderByDesc('started_at')
            ->first();

        return [
            'online' => (bool) ($live['online'] ?? false),
            'framed_ip' => $live['framed_ip'] ?? $session?->framed_ip,
            'router' => $session?->mikrotikServer?->name,
            'session_started' => $live['session_started'] ?? $session?->started_at?->toIso8601String(),
            'session_started_human' => $session?->started_at?->format('d M Y H:i'),
            'uptime' => $session?->formattedDuration(),
            'download_bps' => $live['download_bps'],
            'upload_bps' => $live['upload_bps'],
            'download_human' => BandwidthDirection::formatBps($live['download_bps'] ?? null),
            'upload_human' => BandwidthDirection::formatBps($live['upload_bps'] ?? null),
            'session_download' => BandwidthUsageDaily::formatBytes((int) ($live['total_download'] ?? 0)),
            'session_upload' => BandwidthUsageDaily::formatBytes((int) ($live['total_upload'] ?? 0)),
            'session_download_bytes' => (int) ($live['total_download'] ?? 0),
            'session_upload_bytes' => (int) ($live['total_upload'] ?? 0),
            'today_download' => BandwidthUsageDaily::formatBytes((int) ($live['today_download'] ?? 0)),
            'today_upload' => BandwidthUsageDaily::formatBytes((int) ($live['today_upload'] ?? 0)),
            'last_seen' => $customer->ppp_last_seen_at?->diffForHumans(),
            'last_disconnect' => $customer->lastEndedPppSession?->ended_at?->format('d M Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fromSession(Customer $customer, PppSessionLog $session): array
    {
        return [
            'online' => true,
            'framed_ip' => $session->framed_ip,
            'router' => $session->mikrotikServer?->name,
            'session_started' => $session->started_at?->toIso8601String(),
            'session_started_human' => $session->started_at?->format('d M Y H:i'),
            'uptime' => $session->formattedDuration(),
            'download_bps' => $session->liveDownloadBps(),
            'upload_bps' => $session->liveUploadBps(),
            'download_human' => BandwidthDirection::formatBps($session->liveDownloadBps()),
            'upload_human' => BandwidthDirection::formatBps($session->liveUploadBps()),
            'session_download' => BandwidthUsageDaily::formatBytes((int) $session->bytes_in),
            'session_upload' => BandwidthUsageDaily::formatBytes((int) $session->bytes_out),
            'session_download_bytes' => (int) $session->bytes_in,
            'session_upload_bytes' => (int) $session->bytes_out,
            'today_download' => null,
            'today_upload' => null,
            'last_seen' => $customer->ppp_last_seen_at?->diffForHumans(),
            'last_disconnect' => $customer->lastEndedPppSession?->ended_at?->format('d M Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function offlineSummary(Customer $customer): array
    {
        $lastSession = $customer->relationLoaded('latestPppSession')
            ? $customer->latestPppSession
            : $customer->latestPppSession()->first();

        return [
            'online' => false,
            'framed_ip' => $lastSession?->framed_ip,
            'router' => $lastSession?->mikrotikServer?->name ?? null,
            'session_started' => null,
            'session_started_human' => null,
            'uptime' => null,
            'download_bps' => null,
            'upload_bps' => null,
            'download_human' => '—',
            'upload_human' => '—',
            'session_download' => null,
            'session_upload' => null,
            'session_download_bytes' => 0,
            'session_upload_bytes' => 0,
            'today_download' => null,
            'today_upload' => null,
            'last_seen' => $customer->ppp_last_seen_at?->diffForHumans(),
            'last_disconnect' => $customer->lastEndedPppSession?->ended_at?->format('d M Y H:i')
                ?? $lastSession?->ended_at?->format('d M Y H:i'),
        ];
    }
}
