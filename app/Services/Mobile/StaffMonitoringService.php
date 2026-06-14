<?php

namespace App\Services\Mobile;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\PppSessionLog;
use App\Models\Subzone;
use App\Models\Zone;
use App\Services\Bandwidth\BandwidthCollectionService;
use App\Services\Bandwidth\TenantLiveTrafficService;
use App\Support\BandwidthDirection;
use Illuminate\Database\Eloquent\Builder;

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
        $traffic = app(TenantLiveTrafficService::class)->tick($tenantId);
        $chart = $traffic['chart'];
        if ($chart['labels'] === []) {
            $chart = app(TenantLiveTrafficService::class)->chartFromSamples($tenantId, 2, 120);
        }

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

        $downBps = (int) ($traffic['download_bps'] ?? 0);
        $upBps = (int) ($traffic['upload_bps'] ?? 0);
        $bps = $downBps + $upBps;

        return [
            'online_count' => $count,
            'timestamp' => now()->toIso8601String(),
            'bandwidth_total_bps' => $bps,
            'bandwidth_human' => BandwidthDirection::formatBps($bps > 0 ? $bps : null),
            'download_bps' => $downBps,
            'upload_bps' => $upBps,
            'download_human' => BandwidthDirection::formatBps($downBps > 0 ? $downBps : null),
            'upload_human' => BandwidthDirection::formatBps($upBps > 0 ? $upBps : null),
            'chart' => array_merge($chart, ['granularity' => 'per_second']),
        ];
    }

    /**
     * Legacy mobile "Client Monitoring" list — stats, filters, paginated PPP subscribers.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function clientMonitoringIndex(int $tenantId, array $filters = []): array
    {
        $bandwidth = app(BandwidthCollectionService::class);

        $statsBase = Customer::query()
            ->where('tenant_id', $tenantId)
            ->withMikrotikPpp();

        $total = (clone $statsBase)->count();
        $online = $bandwidth->displayedOnlineCount($tenantId, $statsBase);

        $query = Customer::query()
            ->where('tenant_id', $tenantId)
            ->withMikrotikPpp()
            ->with([
                'zone:id,name',
                'subzone:id,name',
                'area:id,name',
                'package:id,name,download_mbps,price_monthly',
                'mikrotikServer:id,name,host',
                'activePppSession',
                'lastEndedPppSession',
            ])
            ->orderByDesc('is_ppp_online')
            ->orderBy('name');

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%'.$q.'%';
            $query->where(function (Builder $w) use ($like, $q): void {
                $w->where('customer_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('phone', 'like', $like)
                    ->orWhere('radius_username', 'like', $like)
                    ->orWhere('mikrotik_secret_name', 'like', $like);
                if (is_numeric($q)) {
                    $w->orWhere('id', (int) $q);
                }
            });
        }

        if (! empty($filters['mikrotik_server_id'])) {
            $query->where('mikrotik_server_id', (int) $filters['mikrotik_server_id']);
        }

        if (! empty($filters['zone_id'])) {
            $query->where('zone_id', (int) $filters['zone_id']);
        }

        if (! empty($filters['subzone_id'])) {
            $query->where('subzone_id', (int) $filters['subzone_id']);
        }

        $connection = (string) ($filters['connection'] ?? 'all');
        if ($connection === 'online') {
            $bandwidth->applyDisplayedOnlineFilter($query, $tenantId, true);
        } elseif ($connection === 'offline') {
            $bandwidth->applyDisplayedOnlineFilter($query, $tenantId, false);
        }

        $page = max(1, (int) ($filters['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($filters['per_page'] ?? 25)));

        $paginated = $query->paginate($perPage, ['*'], 'page', $page);

        $rows = collect($paginated->items())
            ->map(fn (Customer $c) => $this->clientMonitoringRow($c))
            ->values()
            ->all();

        return [
            'stats' => [
                'total' => $total,
                'online' => $online,
                'offline' => max(0, $total - $online),
            ],
            'filters' => [
                'routers' => MikrotikServer::query()
                    ->where('tenant_id', $tenantId)
                    ->orderBy('name')
                    ->get(['id', 'name', 'host'])
                    ->map(fn (MikrotikServer $s) => [
                        'id' => $s->id,
                        'name' => $s->name,
                        'label' => $s->name.($s->host ? ' · '.$s->host : ''),
                    ])
                    ->values()
                    ->all(),
                'zones' => Zone::query()
                    ->where('tenant_id', $tenantId)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Zone $z) => ['id' => $z->id, 'name' => $z->name])
                    ->values()
                    ->all(),
                'subzones' => Subzone::query()
                    ->where('tenant_id', $tenantId)
                    ->when(! empty($filters['zone_id']), fn (Builder $sq) => $sq->where('zone_id', (int) $filters['zone_id']))
                    ->orderBy('name')
                    ->get(['id', 'name', 'zone_id'])
                    ->map(fn (Subzone $s) => ['id' => $s->id, 'name' => $s->name, 'zone_id' => $s->zone_id])
                    ->values()
                    ->all(),
            ],
            'data' => $rows,
            'meta' => [
                'current_page' => $paginated->currentPage(),
                'last_page' => $paginated->lastPage(),
                'per_page' => $paginated->perPage(),
                'total' => $paginated->total(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function clientMonitoringRow(Customer $customer): array
    {
        $online = $customer->isPppOnline();
        $lastLogout = $customer->lastEndedPppSession?->ended_at ?? $customer->ppp_last_seen_at;
        $packageName = $customer->package?->name;

        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'customer_code' => $customer->customer_code,
            'username' => $customer->pppLoginName(),
            'phone' => $customer->phone,
            'zone' => $customer->zone?->name,
            'subzone' => $customer->subzone?->name,
            'box' => $customer->area?->name,
            'profile' => $packageName ? 'Packages>>'.$packageName : null,
            'package' => $packageName,
            'framed_ip' => $customer->activePppSession?->framed_ip,
            'is_online' => $online,
            'connection_status' => $online ? 'Connected' : 'Offline',
            'billing_status' => ucfirst((string) $customer->status),
            'monthly_bill' => $customer->package?->price_monthly !== null
                ? round((float) $customer->package->price_monthly, 2)
                : null,
            'last_logout' => $lastLogout?->format('d/m/Y h:i:s A'),
            'last_logout_iso' => $lastLogout?->toIso8601String(),
            'mikrotik_server_id' => $customer->mikrotik_server_id,
            'mikrotik_server_name' => $customer->mikrotikServer?->name,
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
