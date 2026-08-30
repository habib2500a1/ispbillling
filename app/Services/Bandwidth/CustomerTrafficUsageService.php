<?php

namespace App\Services\Bandwidth;

use App\Http\Controllers\MikrotikController;
use App\Models\CustomerTrafficUsage;
use App\Models\CustomersInfo;
use App\Models\PPPSecrets;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Snapshot PPPoE byte counters, accumulate daily/monthly GB, reset month at month end.
 */
final class CustomerTrafficUsageService
{
    public const CACHE_PREFIX = 'traffic:usage:';

    /**
     * @param  array<string, array<string, mixed>>  $sessionsByName
     * @param  iterable<int, PPPSecrets>  $secrets
     */
    public function snapshotRouter(string $routerName, array $sessionsByName, iterable $secrets): void
    {
        if (! $this->ready()) {
            return;
        }

        $bytesByUser = $this->byteIndexForRouter($routerName, $sessionsByName);
        foreach ($secrets as $secret) {
            $key = strtolower(trim((string) $secret->username));
            $this->apply($secret, $sessionsByName[$key] ?? null, $bytesByUser[$key] ?? null);
        }
    }

    /**
     * @param  array<string, mixed>|null  $session
     * @param  array{rx?: int, tx?: int}|null  $ifaceBytes
     */
    public function apply(PPPSecrets $secret, ?array $session, ?array $ifaceBytes = null): CustomerTrafficUsage
    {
        $row = $this->rowFor($secret);
        $now = now();
        $dayKey = $now->format('Y-m-d');
        $monthKey = $now->format('Y-m');

        $this->rollPeriods($row, $dayKey, $monthKey);

        $online = is_array($session) && $session !== [];
        $bytes = $this->resolveBytes($session, $ifaceBytes);
        $rx = $bytes['rx'];
        $tx = $bytes['tx'];

        if (! $online) {
            if ($row->online || $row->session_rx_bytes > 0 || $row->session_tx_bytes > 0) {
                $row->last_session_rx_bytes = (int) $row->session_rx_bytes;
                $row->last_session_tx_bytes = (int) $row->session_tx_bytes;
            }
            $row->session_rx_bytes = 0;
            $row->session_tx_bytes = 0;
            $row->prev_rx_bytes = 0;
            $row->prev_tx_bytes = 0;
            $row->session_started_at = null;
            $row->online = false;
            $row->polled_at = $now;
            $row->save();
            $this->remember($row);

            return $row;
        }

        $newSession = ! $row->online
            || ((int) $row->prev_rx_bytes > 0 && $rx < (int) $row->prev_rx_bytes)
            || ((int) $row->prev_tx_bytes > 0 && $tx < (int) $row->prev_tx_bytes);

        if ($newSession && ($row->session_rx_bytes > 0 || $row->session_tx_bytes > 0)) {
            $row->last_session_rx_bytes = (int) $row->session_rx_bytes;
            $row->last_session_tx_bytes = (int) $row->session_tx_bytes;
        }

        $deltaRx = $newSession ? $rx : max(0, $rx - (int) $row->prev_rx_bytes);
        $deltaTx = $newSession ? $tx : max(0, $tx - (int) $row->prev_tx_bytes);

        $row->day_rx_bytes = (int) $row->day_rx_bytes + $deltaRx;
        $row->day_tx_bytes = (int) $row->day_tx_bytes + $deltaTx;
        $row->month_rx_bytes = (int) $row->month_rx_bytes + $deltaRx;
        $row->month_tx_bytes = (int) $row->month_tx_bytes + $deltaTx;
        $row->session_rx_bytes = $rx;
        $row->session_tx_bytes = $tx;
        $row->prev_rx_bytes = $rx;
        $row->prev_tx_bytes = $tx;
        $row->session_started_at = $this->sessionStartedAt($session) ?? $row->session_started_at ?? $now;
        $row->online = true;
        $row->polled_at = $now;
        $row->save();
        $this->remember($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForSecret(?PPPSecrets $secret): array
    {
        if (! $secret || ! $this->ready()) {
            return $this->emptyPresentation();
        }

        $cached = Cache::get(self::CACHE_PREFIX.$secret->id);
        if (is_array($cached) && ($cached['month_key'] ?? null) === now()->format('Y-m')) {
            return $cached;
        }

        $row = $secret->relationLoaded('trafficUsage')
            ? $secret->trafficUsage
            : CustomerTrafficUsage::query()->where('ppp_secret_id', $secret->id)->first();

        return $row ? $this->present($row) : $this->emptyPresentation();
    }

    /**
     * @return array<string, mixed>
     */
    public function presentForCustomer(?CustomersInfo $customer): array
    {
        return $this->presentForSecret($customer?->pppUser);
    }

    /**
     * @param  iterable<int, PPPSecrets>  $secrets
     * @return array<int, array<string, mixed>>
     */
    public function mapForSecrets(iterable $secrets): array
    {
        $out = [];
        foreach ($secrets as $secret) {
            $out[$secret->id] = $this->presentForSecret($secret);
        }

        return $out;
    }

    /**
     * Zero monthly counters when the calendar month has rolled (cache-style month-end delete).
     */
    public function resetEndedMonths(): int
    {
        if (! $this->ready()) {
            return 0;
        }

        $month = now()->format('Y-m');
        $day = now()->format('Y-m-d');
        $count = 0;

        CustomerTrafficUsage::query()
            ->where(function ($q) use ($month) {
                $q->whereNull('month_key')->orWhere('month_key', '!=', $month);
            })
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($month, $day, &$count) {
                foreach ($rows as $row) {
                    $row->month_rx_bytes = 0;
                    $row->month_tx_bytes = 0;
                    $row->month_key = $month;
                    if ($row->day_key !== $day) {
                        $row->day_rx_bytes = 0;
                        $row->day_tx_bytes = 0;
                        $row->day_key = $day;
                    }
                    $row->save();
                    if ($row->ppp_secret_id) {
                        Cache::forget(self::CACHE_PREFIX.$row->ppp_secret_id);
                    }
                    $count++;
                }
            });

        return $count;
    }

    public static function formatBytes(int|float|null $bytes): string
    {
        $bytes = max(0, (float) $bytes);
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2).' GB';
        }
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2).' MB';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return $bytes > 0 ? number_format($bytes, 0).' B' : '0 B';
    }

    /**
     * @param  array<string, array<string, mixed>>  $sessionsByName
     * @return array<string, array{rx: int, tx: int}>
     */
    public function byteIndexForRouter(string $routerName, array $sessionsByName): array
    {
        $index = [];
        foreach ($sessionsByName as $name => $session) {
            $index[strtolower((string) $name)] = $this->bytesFromSession($session);
        }

        try {
            foreach (app(MikrotikController::class)->getPppoeByteStats($routerName) as $user => $bytes) {
                $key = strtolower((string) $user);
                if (($bytes['rx'] ?? 0) > 0 || ($bytes['tx'] ?? 0) > 0) {
                    $index[$key] = [
                        'rx' => (int) ($bytes['rx'] ?? 0),
                        'tx' => (int) ($bytes['tx'] ?? 0),
                    ];
                }
            }
        } catch (\Throwable) {
        }

        return $index;
    }

    public function ready(): bool
    {
        return Schema::hasTable('customer_traffic_usages');
    }

    /**
     * @return array<string, mixed>
     */
    public function emptyPresentation(): array
    {
        $zero = self::formatBytes(0);

        return [
            'online' => false,
            'session_rx' => 0,
            'session_tx' => 0,
            'session_total' => 0,
            'session_rx_label' => $zero,
            'session_tx_label' => $zero,
            'session_total_label' => $zero,
            'last_session_total' => 0,
            'last_session_total_label' => $zero,
            'live_or_last_total' => 0,
            'live_or_last_label' => $zero,
            'live_or_last_title' => __('Last online'),
            'day_rx' => 0,
            'day_tx' => 0,
            'day_total' => 0,
            'day_rx_label' => $zero,
            'day_tx_label' => $zero,
            'day_total_label' => $zero,
            'month_rx' => 0,
            'month_tx' => 0,
            'month_total' => 0,
            'month_rx_label' => $zero,
            'month_tx_label' => $zero,
            'month_total_label' => $zero,
            'month_key' => now()->format('Y-m'),
            'day_key' => now()->format('Y-m-d'),
            'polled_at' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function present(CustomerTrafficUsage $row): array
    {
        $dayKey = now()->format('Y-m-d');
        $monthKey = now()->format('Y-m');

        $dayRx = $row->day_key === $dayKey ? (int) $row->day_rx_bytes : 0;
        $dayTx = $row->day_key === $dayKey ? (int) $row->day_tx_bytes : 0;
        $monthRx = $row->month_key === $monthKey ? (int) $row->month_rx_bytes : 0;
        $monthTx = $row->month_key === $monthKey ? (int) $row->month_tx_bytes : 0;

        $sessionRx = $row->online ? (int) $row->session_rx_bytes : 0;
        $sessionTx = $row->online ? (int) $row->session_tx_bytes : 0;
        $lastRx = (int) $row->last_session_rx_bytes;
        $lastTx = (int) $row->last_session_tx_bytes;
        $liveRx = $row->online ? $sessionRx : $lastRx;
        $liveTx = $row->online ? $sessionTx : $lastTx;

        $payload = [
            'online' => (bool) $row->online,
            'session_rx' => $sessionRx,
            'session_tx' => $sessionTx,
            'session_total' => $sessionRx + $sessionTx,
            'session_rx_label' => self::formatBytes($sessionRx),
            'session_tx_label' => self::formatBytes($sessionTx),
            'session_total_label' => self::formatBytes($sessionRx + $sessionTx),
            'last_session_total' => $lastRx + $lastTx,
            'last_session_total_label' => self::formatBytes($lastRx + $lastTx),
            'live_or_last_total' => $liveRx + $liveTx,
            'live_or_last_label' => self::formatBytes($liveRx + $liveTx),
            'live_or_last_title' => $row->online ? __('This session') : __('Last online'),
            'day_rx' => $dayRx,
            'day_tx' => $dayTx,
            'day_total' => $dayRx + $dayTx,
            'day_rx_label' => self::formatBytes($dayRx),
            'day_tx_label' => self::formatBytes($dayTx),
            'day_total_label' => self::formatBytes($dayRx + $dayTx),
            'month_rx' => $monthRx,
            'month_tx' => $monthTx,
            'month_total' => $monthRx + $monthTx,
            'month_rx_label' => self::formatBytes($monthRx),
            'month_tx_label' => self::formatBytes($monthTx),
            'month_total_label' => self::formatBytes($monthRx + $monthTx),
            'month_key' => $monthKey,
            'day_key' => $dayKey,
            'polled_at' => $row->polled_at?->toDateTimeString(),
        ];

        if ($row->ppp_secret_id) {
            Cache::put(self::CACHE_PREFIX.$row->ppp_secret_id, $payload, now()->endOfMonth()->addHours(6));
        }

        return $payload;
    }

    protected function rowFor(PPPSecrets $secret): CustomerTrafficUsage
    {
        $customer = $secret->relationLoaded('customer') ? $secret->customer : $secret->customer()->first();

        $row = CustomerTrafficUsage::query()->firstOrNew(['ppp_secret_id' => $secret->id]);
        $row->username = $secret->username;
        $row->router_name = $secret->router_name;
        $row->customer_unique_id = $customer?->customer_unique_id;
        if (! $row->saas_operator_id && $customer?->saas_operator_id) {
            $row->saas_operator_id = $customer->saas_operator_id;
        }

        return $row;
    }

    protected function rollPeriods(CustomerTrafficUsage $row, string $dayKey, string $monthKey): void
    {
        if ($row->month_key !== $monthKey) {
            $row->month_rx_bytes = 0;
            $row->month_tx_bytes = 0;
            $row->month_key = $monthKey;
            if ($row->ppp_secret_id) {
                Cache::forget(self::CACHE_PREFIX.$row->ppp_secret_id);
            }
        }

        if ($row->day_key !== $dayKey) {
            $row->day_rx_bytes = 0;
            $row->day_tx_bytes = 0;
            $row->day_key = $dayKey;
        }
    }

    /**
     * @param  array<string, mixed>|null  $session
     * @param  array{rx?: int, tx?: int}|null  $ifaceBytes
     * @return array{rx: int, tx: int}
     */
    protected function resolveBytes(?array $session, ?array $ifaceBytes): array
    {
        $ifaceRx = (int) ($ifaceBytes['rx'] ?? 0);
        $ifaceTx = (int) ($ifaceBytes['tx'] ?? 0);
        if ($ifaceRx > 0 || $ifaceTx > 0) {
            return ['rx' => $ifaceRx, 'tx' => $ifaceTx];
        }

        return $this->bytesFromSession($session);
    }

    /**
     * @param  array<string, mixed>|null  $session
     * @return array{rx: int, tx: int}
     */
    protected function bytesFromSession(?array $session): array
    {
        if (! is_array($session)) {
            return ['rx' => 0, 'tx' => 0];
        }

        $rx = $session['bytes-in'] ?? $session['rx-byte'] ?? $session['rx-bytes'] ?? null;
        $tx = $session['bytes-out'] ?? $session['tx-byte'] ?? $session['tx-bytes'] ?? null;
        if ($rx === null && isset($session['bytes']) && str_contains((string) $session['bytes'], '/')) {
            [$rx, $tx] = array_pad(explode('/', (string) $session['bytes'], 2), 2, 0);
        }

        return [
            'rx' => $this->intBytes($rx),
            'tx' => $this->intBytes($tx),
        ];
    }

    protected function intBytes(mixed $value): int
    {
        if ($value === null || $value === '') {
            return 0;
        }

        return (int) preg_replace('/[^\d]/', '', (string) $value);
    }

    /**
     * @param  array<string, mixed>|null  $session
     */
    protected function sessionStartedAt(?array $session): ?Carbon
    {
        $uptime = $session['uptime'] ?? null;
        if (! is_string($uptime) || $uptime === '') {
            return null;
        }

        $seconds = 0;
        if (preg_match_all('/(\d+)([wdhms])/i', $uptime, $matches, PREG_SET_ORDER)) {
            $units = ['w' => 604800, 'd' => 86400, 'h' => 3600, 'm' => 60, 's' => 1];
            foreach ($matches as $part) {
                $seconds += (int) $part[1] * ($units[strtolower($part[2])] ?? 0);
            }
        }

        return now()->subSeconds(max(0, $seconds));
    }

    protected function remember(CustomerTrafficUsage $row): void
    {
        $this->present($row);
    }
}
