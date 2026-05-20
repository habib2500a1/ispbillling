<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\PppSessionLog;
use App\Support\BandwidthDirection;
use Illuminate\Support\Collection;

final class StaffMonitoringService
{
    /**
     * @return array{total_online: int, data: list<array<string, mixed>>}
     */
    public function onlineClients(int $tenantId, int $limit = 100): array
    {
        $sessions = PppSessionLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->with(['customer.package:id,name'])
            ->orderByDesc('started_at')
            ->limit($limit)
            ->get();

        $fromSessions = $sessions
            ->filter(fn (PppSessionLog $s) => $s->customer !== null)
            ->map(fn (PppSessionLog $s) => $this->sessionRow($s))
            ->values();

        if ($fromSessions->isNotEmpty()) {
            return [
                'total_online' => $fromSessions->count(),
                'data' => $fromSessions->all(),
            ];
        }

        $fallback = Customer::query()
            ->select(['id', 'customer_code', 'name', 'phone', 'status', 'package_id'])
            ->with('package:id,name')
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->limit(300)
            ->get()
            ->filter(fn (Customer $c) => $c->isPppOnline())
            ->take($limit)
            ->values()
            ->map(fn (Customer $c) => [
                'id' => $c->id,
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'phone' => $c->phone,
                'package' => $c->package?->name,
                'status' => $c->status,
                'session_started' => null,
                'online_duration' => null,
                'download_human' => null,
                'upload_human' => null,
                'framed_ip' => null,
            ]);

        return [
            'total_online' => $fallback->count(),
            'data' => $fallback->all(),
        ];
    }

    /**
     * Lightweight snapshot for 1s live chart polling.
     *
     * @return array{online_count: int, timestamp: string, bandwidth_total_bps: int}
     */
    public function liveSnapshot(int $tenantId): array
    {
        $sessions = PppSessionLog::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->get(['peak_rate_in_bps', 'peak_rate_out_bps', 'meta']);

        $count = $sessions->count();
        if ($count === 0) {
            $count = Customer::query()
                ->where('tenant_id', $tenantId)
                ->limit(500)
                ->get()
                ->filter(fn (Customer $c) => $c->isPppOnline())
                ->count();
        }

        $bps = 0;
        foreach ($sessions as $s) {
            $bps += (int) ($s->liveDownloadBps() ?? 0) + (int) ($s->liveUploadBps() ?? 0);
        }

        return [
            'online_count' => $count,
            'timestamp' => now()->toIso8601String(),
            'bandwidth_total_bps' => $bps,
            'bandwidth_human' => BandwidthDirection::formatBps($bps > 0 ? $bps : null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sessionRow(PppSessionLog $s): array
    {
        $c = $s->customer;

        return [
            'id' => $c->id,
            'customer_code' => $c->customer_code,
            'name' => $c->name,
            'phone' => $c->phone,
            'package' => $c->package?->name,
            'status' => $c->status,
            'session_started' => $s->started_at?->toIso8601String(),
            'online_duration' => $s->formattedDuration(),
            'download_human' => BandwidthDirection::formatBps($s->liveDownloadBps()),
            'upload_human' => BandwidthDirection::formatBps($s->liveUploadBps()),
            'framed_ip' => $s->framed_ip,
        ];
    }
}
