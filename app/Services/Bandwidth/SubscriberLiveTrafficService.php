<?php

namespace App\Services\Bandwidth;

use App\Models\Customer;
use App\Models\MikrotikServer;
use App\Models\PppSessionLog;
use App\Services\Mikrotik\MikrotikServerService;
use Illuminate\Support\Facades\Cache;

final class SubscriberLiveTrafficService
{
    private const CHART_PREFIX = 'subscriber_live_chart:';

    private const STATE_PREFIX = 'subscriber_live_state:';

    /**
     * @return array{
     *     chart: array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>},
     *     rx_bps: ?int,
     *     tx_bps: ?int
     * }
     */
    public function tick(Customer $customer): array
    {
        $customerId = (int) $customer->getKey();
        $rates = $this->resolveLiveRates($customer);
        $rxBps = $rates['rx_bps'];
        $txBps = $rates['tx_bps'];

        $chart = Cache::get(self::CHART_PREFIX.$customerId, [
            'labels' => [],
            'download_mbps' => [],
            'upload_mbps' => [],
        ]);

        $chart['labels'][] = now()->format('H:i:s');
        $chart['download_mbps'][] = round(($rxBps ?? 0) / 1_000_000, 4);
        $chart['upload_mbps'][] = round(($txBps ?? 0) / 1_000_000, 4);

        $maxPoints = max(30, (int) config('bandwidth.subscriber_chart_points', 120));
        foreach (['labels', 'download_mbps', 'upload_mbps'] as $key) {
            if (count($chart[$key]) > $maxPoints) {
                $chart[$key] = array_values(array_slice($chart[$key], -$maxPoints));
            }
        }

        Cache::put(self::CHART_PREFIX.$customerId, $chart, now()->addMinutes(15));

        return [
            'chart' => $chart,
            'rx_bps' => $rxBps,
            'tx_bps' => $txBps,
        ];
    }

    /**
     * @return array{rx_bps: ?int, tx_bps: ?int}
     */
    public function resolveLiveRates(Customer $customer): array
    {
        if (! config('bandwidth.subscriber_live_mikrotik_enabled', true)) {
            return $this->ratesFromSessionOrSample($customer);
        }

        if (! $customer->isPppOnline()) {
            return $this->ratesFromSessionOrSample($customer);
        }

        $login = $customer->pppLoginName();
        if ($login === '') {
            return $this->ratesFromSessionOrSample($customer);
        }

        $server = $customer->mikrotikServer;
        if ($server === null && $customer->mikrotik_server_id) {
            $server = MikrotikServer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $customer->tenant_id)
                ->find($customer->mikrotik_server_id);
        }

        if ($server === null || ! $server->is_enabled) {
            return $this->ratesFromSessionOrSample($customer);
        }

        $cacheKey = 'subscriber_mikrotik_rates:'.(int) $customer->getKey();
        $ttlSeconds = max(2, (int) config('bandwidth.subscriber_live_min_interval_seconds', 5));
        $cached = Cache::get($cacheKey);
        if (is_array($cached) && isset($cached['rx_bps'], $cached['tx_bps'])) {
            return [
                'rx_bps' => $cached['rx_bps'],
                'tx_bps' => $cached['tx_bps'],
            ];
        }

        $fetch = app(MikrotikServerService::class)->fetchActivePppSessionForLogin($server, $login);
        if ($fetch['error'] !== null || ! $fetch['found']) {
            return $this->ratesFromSessionOrSample($customer);
        }

        $rx = $fetch['rate_download_bps'];
        $tx = $fetch['rate_upload_bps'];

        if ($rx !== null || $tx !== null) {
            $this->storeCounterState($customer->id, $fetch['download_bytes'], $fetch['upload_bytes']);
            $rates = ['rx_bps' => $rx, 'tx_bps' => $tx];
            Cache::put($cacheKey, $rates, now()->addSeconds($ttlSeconds));

            return $rates;
        }

        $rates = $this->ratesFromByteDelta(
            $customer->id,
            $fetch['download_bytes'],
            $fetch['upload_bytes'],
        );
        Cache::put($cacheKey, $rates, now()->addSeconds($ttlSeconds));

        return $rates;
    }

    /**
     * @return array{rx_bps: ?int, tx_bps: ?int}
     */
    private function ratesFromByteDelta(int $customerId, int $downloadBytes, int $uploadBytes): array
    {
        $stateKey = self::STATE_PREFIX.$customerId;
        $prev = Cache::get($stateKey);
        $now = microtime(true);

        Cache::put($stateKey, [
            'download_bytes' => $downloadBytes,
            'upload_bytes' => $uploadBytes,
            'at' => $now,
        ], now()->addMinutes(15));

        if (! is_array($prev)) {
            return ['rx_bps' => 0, 'tx_bps' => 0];
        }

        $elapsed = $now - (float) ($prev['at'] ?? $now);
        $minInterval = max(0.5, (float) config('bandwidth.subscriber_live_min_interval_seconds', 1));
        if ($elapsed < $minInterval) {
            return [
                'rx_bps' => $prev['rx_bps'] ?? 0,
                'tx_bps' => $prev['tx_bps'] ?? 0,
            ];
        }

        $deltaDown = max(0, $downloadBytes - (int) ($prev['download_bytes'] ?? 0));
        $deltaUp = max(0, $uploadBytes - (int) ($prev['upload_bytes'] ?? 0));
        $rxBps = (int) round(($deltaDown * 8) / $elapsed);
        $txBps = (int) round(($deltaUp * 8) / $elapsed);

        $maxBps = (int) config('bandwidth.max_sane_rate_bps', 10_000_000_000);
        if ($rxBps > $maxBps || $txBps > $maxBps) {
            return ['rx_bps' => 0, 'tx_bps' => 0];
        }

        Cache::put($stateKey, [
            'download_bytes' => $downloadBytes,
            'upload_bytes' => $uploadBytes,
            'at' => $now,
            'rx_bps' => $rxBps,
            'tx_bps' => $txBps,
        ], now()->addMinutes(15));

        return ['rx_bps' => $rxBps, 'tx_bps' => $txBps];
    }

    private function storeCounterState(int $customerId, int $downloadBytes, int $uploadBytes): void
    {
        Cache::put(self::STATE_PREFIX.$customerId, [
            'download_bytes' => $downloadBytes,
            'upload_bytes' => $uploadBytes,
            'at' => microtime(true),
        ], now()->addMinutes(15));
    }

    /**
     * @return array{rx_bps: ?int, tx_bps: ?int}
     */
    private function ratesFromSessionOrSample(Customer $customer): array
    {
        $rates = BandwidthCollectionService::currentSubscriberRates((int) $customer->getKey());

        return [
            'rx_bps' => $rates['rx_bps'],
            'tx_bps' => $rates['tx_bps'],
        ];
    }

    public function clearChart(int $customerId): void
    {
        Cache::forget(self::CHART_PREFIX.$customerId);
        Cache::forget(self::STATE_PREFIX.$customerId);
    }

    public function maybePersistSessionRates(Customer $customer, ?int $rxBps, ?int $txBps): void
    {
        if ($rxBps === null && $txBps === null) {
            return;
        }

        $every = max(1, (int) config('bandwidth.subscriber_session_persist_every_ticks', 5));
        $key = 'subscriber_live_persist_tick:'.$customer->getKey();
        $tick = (int) Cache::increment($key);
        Cache::put($key, $tick, now()->addMinutes(15));

        if ($tick % $every !== 0) {
            return;
        }

        $session = $customer->relationLoaded('activePppSession')
            ? $customer->activePppSession
            : PppSessionLog::query()
                ->where('customer_id', $customer->id)
                ->where('status', 'active')
                ->whereNull('ended_at')
                ->orderByDesc('id')
                ->first();

        if ($session === null) {
            return;
        }

        $meta = $session->meta ?? [];
        $meta['rate_download_bps'] = $rxBps;
        $meta['rate_upload_bps'] = $txBps;
        $session->forceFill(['meta' => $meta])->saveQuietly();
    }
}
