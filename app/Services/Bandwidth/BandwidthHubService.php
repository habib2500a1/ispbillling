<?php

namespace App\Services\Bandwidth;

use App\Http\Controllers\MikrotikController;
use App\Models\PackageList;
use App\Models\PPPSecrets;
use App\Models\RouterList;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Bandwidth Hub — network overview + optional live traffic chart (Code Pagol).
 * Reuses MikrotikController; chart points stored in cache (no schema).
 */
final class BandwidthHubService
{
    private const CHART_PREFIX = 'bandwidth_hub_chart:';

    /**
     * @return array<string, mixed>
     */
    public function payload(?string $routerName = null, ?string $interface = null): array
    {
        $routers = RouterList::query()->orderBy('router_name')->get();
        $connected = $routers->where('action', 'connected')->values();

        $selectedRouter = $routerName
            ?: ($connected->first()?->router_name ?? $routers->first()?->router_name);

        $interfaces = [];
        $live = ['rx_bps' => 0, 'tx_bps' => 0, 'rx_mbps' => 0.0, 'tx_mbps' => 0.0, 'error' => null];
        $activePpp = null;
        $chart = $this->emptyChart();

        if ($selectedRouter && $connected->contains(fn ($r) => $r->router_name === $selectedRouter)) {
            try {
                $ctrl = app(MikrotikController::class);
                $interfaces = collect($ctrl->getInterfaces($selectedRouter))
                    ->map(fn ($i) => is_array($i) ? ($i['name'] ?? null) : null)
                    ->filter()
                    ->values()
                    ->all();

                if (! $interface || ! in_array($interface, $interfaces, true)) {
                    $interface = collect($interfaces)->first(fn ($n) => str_contains((string) $n, 'ether'))
                        ?? ($interfaces[0] ?? null);
                }

                if ($interface) {
                    $tick = $this->tickLive($selectedRouter, $interface);
                    $live = $tick['live'];
                    $chart = $tick['chart'];
                }

                try {
                    $activePpp = count($ctrl->getActivePppSessions($selectedRouter));
                } catch (\Throwable) {
                    $activePpp = null;
                }
            } catch (\Throwable $e) {
                Log::warning('Bandwidth hub live failed: '.$e->getMessage());
                $live['error'] = $e->getMessage();
            }
        }

        $pppTotal = PPPSecrets::query()->count();
        $pppOnline = PPPSecrets::query()
            ->where(function ($q) {
                $q->whereIn('status', ['online', 'active'])
                    ->orWhere(function ($q2) {
                        $q2->whereNotNull('uptime')
                            ->where('uptime', '!=', '')
                            ->where('uptime', '!=', '0s');
                    });
            })
            ->count();

        $packages = PackageList::query()
            ->selectRaw('COALESCE(NULLIF(speed,""), NULLIF(mikrotik_rate_limit,""), "unknown") as speed_label, COUNT(*) as cnt')
            ->groupBy('speed_label')
            ->orderByDesc('cnt')
            ->limit(12)
            ->get()
            ->map(fn ($r) => [
                'label' => (string) $r->speed_label,
                'count' => (int) $r->cnt,
            ])
            ->all();

        $pppByRouter = PPPSecrets::query()
            ->selectRaw('COALESCE(NULLIF(router_name,""),"—") as router, COUNT(*) as cnt')
            ->groupBy('router')
            ->orderByDesc('cnt')
            ->limit(12)
            ->get()
            ->map(fn ($r) => [
                'router' => (string) $r->router,
                'count' => (int) $r->cnt,
            ])
            ->all();

        return [
            'updated_at' => now()->toIso8601String(),
            'stats' => [
                'routers' => $routers->count(),
                'connected' => $connected->count(),
                'ppp_total' => $pppTotal,
                'ppp_online_db' => $pppOnline,
                'ppp_active_live' => $activePpp,
                'packages' => PackageList::query()->count(),
            ],
            'routers' => $routers->map(fn (RouterList $r) => [
                'name' => $r->router_name,
                'ip' => $r->ip_address,
                'action' => $r->action,
                'connected' => $r->action === 'connected',
            ])->all(),
            'selected_router' => $selectedRouter,
            'selected_interface' => $interface,
            'interfaces' => $interfaces,
            'live' => $live,
            'chart' => $chart,
            'packages' => $packages,
            'ppp_by_router' => $pppByRouter,
        ];
    }

    /**
     * @return array{live: array<string, mixed>, chart: array<string, list<float|string>>}
     */
    public function tickLive(string $routerName, string $interface): array
    {
        $ctrl = app(MikrotikController::class);
        $data = $ctrl->getLiveTraffic($routerName, $interface);
        $rx = (int) ($data['rx-bits-per-second'] ?? 0);
        $tx = (int) ($data['tx-bits-per-second'] ?? 0);

        $key = self::CHART_PREFIX.md5($routerName.'|'.$interface);
        $chart = Cache::get($key, $this->emptyChart());
        $chart['labels'][] = now()->format('H:i:s');
        $chart['rx_mbps'][] = round($rx / 1_000_000, 3);
        $chart['tx_mbps'][] = round($tx / 1_000_000, 3);

        $max = 60;
        foreach (['labels', 'rx_mbps', 'tx_mbps'] as $k) {
            if (count($chart[$k]) > $max) {
                $chart[$k] = array_values(array_slice($chart[$k], -$max));
            }
        }
        Cache::put($key, $chart, now()->addMinutes(20));

        return [
            'live' => [
                'rx_bps' => $rx,
                'tx_bps' => $tx,
                'rx_mbps' => round($rx / 1_000_000, 3),
                'tx_mbps' => round($tx / 1_000_000, 3),
                'error' => null,
            ],
            'chart' => $chart,
        ];
    }

    /**
     * @return array{labels: list<string>, rx_mbps: list<float>, tx_mbps: list<float>}
     */
    private function emptyChart(): array
    {
        return [
            'labels' => [],
            'rx_mbps' => [],
            'tx_mbps' => [],
        ];
    }
}
