<?php

namespace App\Services\Bandwidth;

use App\Models\BandwidthSample;
use App\Models\WanBandwidthSample;
use App\Models\PppSessionLog;
use App\Models\BandwidthUsageDaily;
use App\Models\Customer;
use App\Models\Device;
use App\Models\MikrotikServer;
use App\Services\Mikrotik\MikrotikFleetCoordinator;
use App\Services\Mikrotik\MikrotikServerService;
use App\Services\Radius\RadiusAccountingService;
use App\Support\BandwidthDirection;
use App\Support\CustomerPppLoginResolver;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class BandwidthCollectionService
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
        private readonly MikrotikFleetCoordinator $fleet,
        private readonly RadiusAccountingService $radius,
        private readonly AbuseDetectionService $abuse,
    ) {}

    /**
     * @return array{
     *   samples: int,
     *   sessions_open: int,
     *   sessions_closed: int,
     *   api_sessions: int,
     *   radius_sessions: int,
     *   merged_users: int,
     *   matched_subscribers: int,
     *   unmatched_logins: list<string>,
     *   api_errors: list<string>,
     *   api_ok: bool
     * }
     */
    public function collectForTenant(int $tenantId): array
    {
        if (config('sync.fast_mode', true)) {
            return $this->collectForTenantFast($tenantId);
        }

        $now = now();

        $apiResult = $this->collectApiSessions($tenantId);
        $apiSessions = $apiResult['sessions'];
        $apiOk = $apiResult['api_ok'];

        $radiusSessions = config('radius.merge_with_api', true)
            ? $this->collectRadiusSessions($tenantId)
            : [];

        $merged = $this->mergeSessions($apiSessions, $radiusSessions);

        $canUpdateOnlineFlags = $apiOk || $radiusSessions !== [];

        $samples = 0;
        $seenKeys = [];
        $onlineCustomerIds = [];
        $matchedSubscribers = 0;
        $unmatchedLogins = [];

        foreach ($merged as $session) {
            $username = (string) $session['username'];
            $customer = $this->resolveCustomer($tenantId, $username, $session['mikrotik_server_id'] ?? null);
            if ($customer === null) {
                $unmatchedLogins[] = $username;

                continue;
            }

            $matchedSubscribers++;
            $onlineCustomerIds[$customer->id] = true;
            $sessionKey = $session['session_key'];
            $seenKeys[] = $sessionKey;

            $downloadBytes = (int) ($session['download_bytes'] ?? 0);
            $uploadBytes = (int) ($session['upload_bytes'] ?? 0);
            $callerId = $session['caller_id'];
            $framedIp = (string) ($session['framed_ip'] ?? '');
            $serverId = $session['mikrotik_server_id'] ?? null;

            $device = $this->matchDevice($customer, $callerId);

            [$rateDownload, $rateUpload] = $this->computeRates($sessionKey, $downloadBytes, $uploadBytes, $now);
            $rateDownload = $rateDownload ?? $session['instant_rate_download_bps'] ?? null;
            $rateUpload = $rateUpload ?? $session['instant_rate_upload_bps'] ?? null;

            BandwidthSample::query()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'mikrotik_server_id' => $serverId,
                'device_id' => $device?->id,
                'session_key' => $sessionKey,
                'username' => $username,
                'bytes_in' => $downloadBytes,
                'bytes_out' => $uploadBytes,
                'rate_in_bps' => $rateDownload,
                'rate_out_bps' => $rateUpload,
                'framed_ip' => $framedIp !== '' ? $framedIp : null,
                'caller_id' => $callerId,
                'sampled_at' => $now,
            ]);
            $samples++;

            $this->upsertSessionLog(
                $tenantId,
                $customer,
                $serverId ? MikrotikServer::withoutGlobalScopes()->find($serverId) : null,
                $device,
                $sessionKey,
                $username,
                $framedIp,
                $callerId,
                $downloadBytes,
                $uploadBytes,
                $rateDownload,
                $rateUpload,
                $now,
                $session['sources'],
            );

            $this->rollupDaily($customer, $downloadBytes, $uploadBytes, $rateDownload, $rateUpload, $now);
        }

        $closed = 0;
        if ($canUpdateOnlineFlags) {
            $this->syncCustomerOnlineFlags($tenantId, array_keys($onlineCustomerIds));
            $closed = $this->closeStaleSessions($tenantId, $seenKeys, $now);
        }

        try {
            $this->abuse->evaluateTenant($tenantId);
        } catch (\Throwable $e) {
            Log::warning('bandwidth.abuse_eval_failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
        }

        $wanSamples = $this->collectWanInterfaces($tenantId, $now);

        $this->pruneOldSamples();

        $apiCount = count($apiSessions);
        $radiusCount = count($radiusSessions);
        $sessionsOpen = count(array_unique($seenKeys));

        BandwidthSyncStatus::store($tenantId, [
            'api' => [
                'ok' => $apiOk && ($apiCount > 0 || ! $this->tenantHasEnabledMikrotik($tenantId)),
                'sessions' => $apiCount,
                'error' => $apiResult['errors'][0] ?? ($apiCount === 0 && $this->tenantHasEnabledMikrotik($tenantId) && ! $apiOk
                    ? 'MikroTik API unreachable — online list not updated'
                    : ($apiCount === 0 && $this->tenantHasEnabledMikrotik($tenantId)
                        ? 'No active PPP sessions on router (or none matched)'
                        : null)),
            ],
            'radius' => array_merge($this->safeRadiusPing(), [
                'sessions' => $radiusCount,
            ]),
            'merged_active' => $sessionsOpen,
            'matched_subscribers' => $matchedSubscribers,
            'unmatched_logins' => array_values(array_unique($unmatchedLogins)),
            'api_errors' => $apiResult['errors'],
            'samples' => $samples,
            'wan_samples' => $wanSamples,
        ]);

        return [
            'samples' => $samples,
            'wan_samples' => $wanSamples,
            'sessions_open' => $sessionsOpen,
            'sessions_closed' => $closed,
            'api_sessions' => $apiCount,
            'radius_sessions' => $radiusCount,
            'merged_users' => count($merged),
            'matched_subscribers' => $matchedSubscribers,
            'unmatched_logins' => array_values(array_unique($unmatchedLogins)),
            'api_errors' => $apiResult['errors'],
            'api_ok' => $apiOk,
        ];
    }

    /**
     * Optimized path: in-memory lookups, batch sample inserts, throttled abuse/WAN/prune.
     *
     * @return array<string, mixed>
     */
    private function collectForTenantFast(int $tenantId): array
    {
        $now = now();
        CustomerPppLoginResolver::clearIndexCache();
        $customerIndex = CustomerPppLoginResolver::indexForTenant($tenantId);

        $apiResult = $this->collectApiSessions($tenantId);
        $apiSessions = $apiResult['sessions'];
        $apiOk = $apiResult['api_ok'];

        $radiusSessions = config('radius.merge_with_api', true)
            ? $this->collectRadiusSessions($tenantId)
            : [];

        $merged = $this->mergeSessions($apiSessions, $radiusSessions);
        $canUpdateOnlineFlags = $apiOk || $radiusSessions !== [];

        $serversById = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('id');

        $sessionKeys = array_values(array_unique(array_map(
            fn (array $s): string => (string) $s['session_key'],
            $merged,
        )));

        $prevSamples = $sessionKeys === []
            ? collect()
            : BandwidthSample::query()
                ->whereIn('session_key', $sessionKeys)
                ->orderByDesc('sampled_at')
                ->get()
                ->unique('session_key')
                ->keyBy('session_key');

        $logsByKey = $sessionKeys === []
            ? collect()
            : PppSessionLog::query()
                ->whereIn('session_key', $sessionKeys)
                ->get()
                ->keyBy('session_key');

        $customerIds = array_values(array_unique(array_map(
            fn (Customer $c): int => (int) $c->id,
            array_values($customerIndex),
        )));

        $devicesByCustomer = $customerIds === []
            ? collect()
            : Device::query()
                ->where('type', '!=', 'olt')
                ->whereIn('customer_id', $customerIds)
                ->get()
                ->groupBy('customer_id');

        $samples = 0;
        $seenKeys = [];
        $onlineCustomerIds = [];
        $matchedSubscribers = 0;
        $unmatchedLogins = [];
        $sampleRows = [];
        $dailyByCustomer = [];
        $interval = max(1, (int) config('bandwidth.poll_interval_minutes', 5)) * 60;

        foreach ($merged as $session) {
            $username = (string) $session['username'];
            $customer = CustomerPppLoginResolver::resolve(
                $tenantId,
                $username,
                isset($session['mikrotik_server_id']) ? (int) $session['mikrotik_server_id'] : null,
            );
            if ($customer === null) {
                $unmatchedLogins[] = $username;

                continue;
            }

            $matchedSubscribers++;
            $onlineCustomerIds[$customer->id] = true;
            $sessionKey = $session['session_key'];
            $seenKeys[] = $sessionKey;

            $downloadBytes = (int) ($session['download_bytes'] ?? 0);
            $uploadBytes = (int) ($session['upload_bytes'] ?? 0);
            $callerId = $session['caller_id'];
            $framedIp = (string) ($session['framed_ip'] ?? '');
            $serverId = $session['mikrotik_server_id'] ?? null;
            $server = $serverId ? $serversById->get($serverId) : null;

            $device = $this->matchDeviceFromPreload($devicesByCustomer->get($customer->id), $callerId);

            [$rateDownload, $rateUpload] = $this->computeRatesFromPrevious(
                $prevSamples->get($sessionKey),
                $downloadBytes,
                $uploadBytes,
                $now,
            );
            $rateDownload = $rateDownload ?? $session['instant_rate_download_bps'] ?? null;
            $rateUpload = $rateUpload ?? $session['instant_rate_upload_bps'] ?? null;

            $sampleRows[] = [
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'mikrotik_server_id' => $serverId,
                'device_id' => $device?->id,
                'session_key' => $sessionKey,
                'username' => $username,
                'bytes_in' => $downloadBytes,
                'bytes_out' => $uploadBytes,
                'rate_in_bps' => $rateDownload,
                'rate_out_bps' => $rateUpload,
                'framed_ip' => $framedIp !== '' ? $framedIp : null,
                'caller_id' => $callerId,
                'sampled_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $samples++;

            $this->upsertSessionLogFast(
                $logsByKey,
                $tenantId,
                $customer,
                $server,
                $device,
                $sessionKey,
                $username,
                $framedIp,
                $callerId,
                $downloadBytes,
                $uploadBytes,
                $rateDownload,
                $rateUpload,
                $now,
                $session['sources'] ?? [],
            );

            $cid = (int) $customer->id;
            if (! isset($dailyByCustomer[$cid])) {
                $dailyByCustomer[$cid] = [
                    'customer' => $customer,
                    'peak_in' => 0,
                    'peak_out' => 0,
                    'online_seconds' => 0,
                ];
            }
            if ($rateDownload !== null && $rateDownload > 0) {
                $dailyByCustomer[$cid]['peak_in'] = max($dailyByCustomer[$cid]['peak_in'], $rateDownload);
            }
            if ($rateUpload !== null && $rateUpload > 0) {
                $dailyByCustomer[$cid]['peak_out'] = max($dailyByCustomer[$cid]['peak_out'], $rateUpload);
            }
            $dailyByCustomer[$cid]['online_seconds'] += $interval;
        }

        foreach (array_chunk($sampleRows, (int) config('sync.bandwidth_insert_batch', 250)) as $chunk) {
            BandwidthSample::query()->insert($chunk);
        }

        foreach ($logsByKey as $log) {
            if ($log->exists && $log->isDirty()) {
                $log->saveQuietly();
            }
        }

        $this->rollupDailyBatch($dailyByCustomer, $now);

        $closed = 0;
        if ($canUpdateOnlineFlags) {
            $this->syncCustomerOnlineFlags($tenantId, array_keys($onlineCustomerIds));
            $closed = $this->closeStaleSessions($tenantId, $seenKeys, $now);
        }

        $abuseMinutes = (int) config('sync.abuse_eval_interval_minutes', 15);
        if ($abuseMinutes <= 0 || ! Cache::has('bandwidth_abuse_eval_'.$tenantId)) {
            try {
                $this->abuse->evaluateTenant($tenantId);
                Cache::put('bandwidth_abuse_eval_'.$tenantId, true, now()->addMinutes(max(5, $abuseMinutes)));
            } catch (\Throwable $e) {
                Log::warning('bandwidth.abuse_eval_failed', ['tenant_id' => $tenantId, 'error' => $e->getMessage()]);
            }
        }

        $wanSamples = config('sync.wan_on_bandwidth_collect', false)
            ? $this->collectWanInterfaces($tenantId, $now)
            : 0;

        if (Cache::missing('bandwidth_prune_lock')) {
            $this->pruneOldSamples();
            Cache::put('bandwidth_prune_lock', true, now()->addHour());
        }

        $apiCount = count($apiSessions);
        $radiusCount = count($radiusSessions);
        $sessionsOpen = count(array_unique($seenKeys));

        BandwidthSyncStatus::store($tenantId, [
            'api' => [
                'ok' => $apiOk && ($apiCount > 0 || ! $this->tenantHasEnabledMikrotik($tenantId)),
                'sessions' => $apiCount,
                'error' => $apiResult['errors'][0] ?? null,
            ],
            'radius' => array_merge($this->safeRadiusPing(), ['sessions' => $radiusCount]),
            'merged_active' => $sessionsOpen,
            'matched_subscribers' => $matchedSubscribers,
            'unmatched_logins' => array_values(array_unique($unmatchedLogins)),
            'api_errors' => $apiResult['errors'],
            'samples' => $samples,
            'wan_samples' => $wanSamples,
            'fast_mode' => true,
        ]);

        CustomerPppLoginResolver::clearIndexCache();

        return [
            'samples' => $samples,
            'wan_samples' => $wanSamples,
            'sessions_open' => $sessionsOpen,
            'sessions_closed' => $closed,
            'api_sessions' => $apiCount,
            'radius_sessions' => $radiusCount,
            'merged_users' => count($merged),
            'matched_subscribers' => $matchedSubscribers,
            'unmatched_logins' => array_values(array_unique($unmatchedLogins)),
            'api_errors' => $apiResult['errors'],
            'api_ok' => $apiOk,
        ];
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Device>|null  $devices
     */
    private function matchDeviceFromPreload($devices, ?string $mac): ?Device
    {
        if ($mac === null || $devices === null) {
            return null;
        }

        $normalized = strtolower($mac);

        return $devices->first(
            fn (Device $d): bool => strtolower(preg_replace('/[^a-f0-9]/', '', (string) $d->mac_address) ?? '') === $normalized
        );
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function computeRatesFromPrevious(?BandwidthSample $prev, int $downloadBytes, int $uploadBytes, Carbon $now): array
    {
        if ($prev === null) {
            return [null, null];
        }

        $seconds = $prev->sampled_at->diffInSeconds($now);
        $minInterval = max(30, (int) config('bandwidth.min_rate_interval_seconds', 45));
        if ($seconds < $minInterval) {
            return [null, null];
        }

        $deltaDown = max(0, $downloadBytes - (int) $prev->bytes_in);
        $deltaUp = max(0, $uploadBytes - (int) $prev->bytes_out);
        $rateDown = (int) round(($deltaDown * 8) / $seconds);
        $rateUp = (int) round(($deltaUp * 8) / $seconds);
        $maxBps = (int) config('bandwidth.max_sane_rate_bps', 10_000_000_000);
        if ($rateDown > $maxBps || $rateUp > $maxBps) {
            return [null, null];
        }

        return [$rateDown, $rateUp];
    }

    /**
     * @param  \Illuminate\Support\Collection<string, PppSessionLog>  $logsByKey
     * @param  array<string, string>  $sources
     */
    private function upsertSessionLogFast(
        $logsByKey,
        int $tenantId,
        Customer $customer,
        ?MikrotikServer $server,
        ?Device $device,
        string $sessionKey,
        string $username,
        string $framedIp,
        ?string $callerId,
        int $downloadBytes,
        int $uploadBytes,
        ?int $rateDownload,
        ?int $rateUpload,
        Carbon $now,
        array $sources = [],
    ): void {
        $log = $logsByKey->get($sessionKey);
        $metaRates = [
            'rate_download_bps' => $rateDownload,
            'rate_upload_bps' => $rateUpload,
        ];

        if ($log === null) {
            $log = PppSessionLog::query()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'mikrotik_server_id' => $server?->id,
                'device_id' => $device?->id,
                'session_key' => $sessionKey,
                'username' => $username,
                'framed_ip' => $framedIp !== '' ? $framedIp : null,
                'caller_id' => $callerId,
                'bytes_in' => $downloadBytes,
                'bytes_out' => $uploadBytes,
                'peak_rate_in_bps' => $rateDownload ?? 0,
                'peak_rate_out_bps' => $rateUpload ?? 0,
                'started_at' => $now,
                'status' => 'active',
                'meta' => array_merge(['sources' => $sources], $metaRates),
            ]);
            $logsByKey->put($sessionKey, $log);

            return;
        }

        $peakDown = max((int) $log->peak_rate_in_bps, (int) ($rateDownload ?? 0));
        $peakUp = max((int) $log->peak_rate_out_bps, (int) ($rateUpload ?? 0));
        $meta = $log->meta ?? [];
        $meta['sources'] = array_merge($meta['sources'] ?? [], $sources);
        $meta['rate_download_bps'] = $rateDownload;
        $meta['rate_upload_bps'] = $rateUpload;

        $log->forceFill([
            'customer_id' => $customer->id,
            'bytes_in' => $downloadBytes,
            'bytes_out' => $uploadBytes,
            'peak_rate_in_bps' => $peakDown,
            'peak_rate_out_bps' => $peakUp,
            'framed_ip' => $framedIp !== '' ? $framedIp : $log->framed_ip,
            'caller_id' => $callerId ?? $log->caller_id,
            'device_id' => $device?->id ?? $log->device_id,
            'mikrotik_server_id' => $server?->id ?? $log->mikrotik_server_id,
            'status' => 'active',
            'ended_at' => null,
            'meta' => $meta,
        ]);
    }

    /**
     * @param  array<int, array{customer: Customer, peak_in: int, peak_out: int, online_seconds: int}>  $dailyByCustomer
     */
    private function rollupDailyBatch(array $dailyByCustomer, Carbon $now): void
    {
        if ($dailyByCustomer === []) {
            return;
        }

        $date = $now->toDateString();
        $customerIds = array_keys($dailyByCustomer);
        $existing = BandwidthUsageDaily::query()
            ->whereIn('customer_id', $customerIds)
            ->where('usage_date', $date)
            ->get()
            ->keyBy('customer_id');

        $activeCounts = PppSessionLog::query()
            ->select('customer_id', DB::raw('count(*) as c'))
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'active')
            ->groupBy('customer_id')
            ->pluck('c', 'customer_id');

        foreach ($dailyByCustomer as $cid => $acc) {
            $daily = $existing->get($cid) ?? new BandwidthUsageDaily([
                'customer_id' => $cid,
                'usage_date' => $date,
                'tenant_id' => $acc['customer']->tenant_id,
                'bytes_in' => 0,
                'bytes_out' => 0,
                'peak_rate_in_bps' => 0,
                'peak_rate_out_bps' => 0,
                'online_seconds' => 0,
                'session_count' => 0,
            ]);

            $interval = $acc['online_seconds'];
            if ($acc['peak_in'] > 0) {
                $daily->bytes_in = (int) $daily->bytes_in + (int) round($acc['peak_in'] * $interval / 8);
            }
            if ($acc['peak_out'] > 0) {
                $daily->bytes_out = (int) $daily->bytes_out + (int) round($acc['peak_out'] * $interval / 8);
            }
            $daily->online_seconds = (int) $daily->online_seconds + $interval;
            $daily->peak_rate_in_bps = max((int) $daily->peak_rate_in_bps, $acc['peak_in']);
            $daily->peak_rate_out_bps = max((int) $daily->peak_rate_out_bps, $acc['peak_out']);
            $daily->session_count = (int) ($activeCounts[$cid] ?? 0);
            $daily->saveQuietly();
        }
    }

    /**
     * @return array{sessions: list<array<string, mixed>>, api_ok: bool, errors: list<string>}
     */
    private function collectApiSessions(int $tenantId): array
    {
        if (! $this->tenantHasEnabledMikrotik($tenantId)) {
            return [
                'sessions' => [],
                'api_ok' => false,
                'errors' => ['No enabled MikroTik server for this tenant. Add one under Network → MikroTik.'],
            ];
        }

        $result = $this->fleet->collectActiveSessionsForTenant($tenantId);

        return [
            'sessions' => $result['sessions'],
            'api_ok' => $result['api_ok'],
            'errors' => $result['errors'],
        ];
    }

    private function tenantHasEnabledMikrotik(int $tenantId): bool
    {
        return MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->exists();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function collectRadiusSessions(int $tenantId): array
    {
        if (! $this->radius->isEnabled()) {
            return [];
        }

        $sessions = [];
        foreach ($this->radius->fetchActiveSessions() as $row) {
            $username = trim((string) $row['username']);
            if ($username === '') {
                continue;
            }

            $counters = BandwidthDirection::fromRadiusCounters(
                (int) $row['bytes_in'],
                (int) $row['bytes_out'],
            );
            $sessions[] = [
                'username' => $username,
                'session_key' => $this->radius->sessionKey((string) $row['acctsessionid']),
                'mikrotik_server_id' => null,
                'framed_ip' => (string) ($row['framedipaddress'] ?? ''),
                'caller_id' => $this->normalizeMac((string) ($row['callingstationid'] ?? '')),
                'download_bytes' => $counters['download_bytes'],
                'upload_bytes' => $counters['upload_bytes'],
                'sources' => ['radius' => now()->toIso8601String()],
            ];
        }

        return $sessions;
    }

    /**
     * @param  list<array<string, mixed>>  $apiSessions
     * @param  list<array<string, mixed>>  $radiusSessions
     * @return list<array<string, mixed>>
     */
    private function mergeSessions(array $apiSessions, array $radiusSessions): array
    {
        $byUser = [];

        foreach ($radiusSessions as $session) {
            $key = $this->sessionMergeKey($session);
            $byUser[$key] = $session;
        }

        foreach ($apiSessions as $session) {
            $key = $this->sessionMergeKey($session);
            if (! isset($byUser[$key])) {
                $byUser[$key] = $session;

                continue;
            }

            $existing = $byUser[$key];
            $preferApi = (bool) config('radius.prefer_api_for_live_rates', true);

            $byUser[$key] = [
                'username' => $session['username'],
                'session_key' => $preferApi ? $session['session_key'] : $existing['session_key'],
                'mikrotik_server_id' => $session['mikrotik_server_id'] ?? $existing['mikrotik_server_id'],
                'framed_ip' => $session['framed_ip'] ?: $existing['framed_ip'],
                'caller_id' => $session['caller_id'] ?? $existing['caller_id'],
                'download_bytes' => $preferApi
                    ? max((int) $session['download_bytes'], (int) $existing['download_bytes'])
                    : max((int) $existing['download_bytes'], (int) $session['download_bytes']),
                'upload_bytes' => $preferApi
                    ? max((int) $session['upload_bytes'], (int) $existing['upload_bytes'])
                    : max((int) $existing['upload_bytes'], (int) $session['upload_bytes']),
                'instant_rate_download_bps' => $session['instant_rate_download_bps'] ?? $existing['instant_rate_download_bps'] ?? null,
                'instant_rate_upload_bps' => $session['instant_rate_upload_bps'] ?? $existing['instant_rate_upload_bps'] ?? null,
                'sources' => array_merge($existing['sources'] ?? [], $session['sources'] ?? []),
            ];
        }

        return array_values($byUser);
    }

    private function resolveCustomer(int $tenantId, string $username, mixed $mikrotikServerId = null): ?Customer
    {
        $serverId = is_numeric($mikrotikServerId) ? (int) $mikrotikServerId : null;

        return CustomerPppLoginResolver::resolve($tenantId, $username, $serverId > 0 ? $serverId : null);
    }

    /**
     * @param  array<string, mixed>  $session
     */
    private function sessionMergeKey(array $session): string
    {
        $login = CustomerPppLoginResolver::normalize((string) $session['username']);
        $serverId = $session['mikrotik_server_id'] ?? null;

        return ($serverId !== null && (int) $serverId > 0)
            ? CustomerPppLoginResolver::serverScopedKey((int) $serverId, $login)
            : $login;
    }

    private function normalizeMac(string $mac): ?string
    {
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac) ?? '');

        return strlen($mac) >= 12 ? $mac : null;
    }

    private function matchDevice(Customer $customer, ?string $mac): ?Device
    {
        if ($mac === null) {
            return null;
        }

        $normalized = strtolower($mac);

        return Device::query()
            ->where('customer_id', $customer->id)
            ->where('type', '!=', 'olt')
            ->get()
            ->first(fn (Device $d): bool => strtolower(preg_replace('/[^a-f0-9]/', '', (string) $d->mac_address) ?? '') === $normalized);
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function computeRates(string $sessionKey, int $downloadBytes, int $uploadBytes, Carbon $now): array
    {
        $prev = BandwidthSample::query()
            ->where('session_key', $sessionKey)
            ->orderByDesc('sampled_at')
            ->first();

        if ($prev === null) {
            return [null, null];
        }

        $seconds = $prev->sampled_at->diffInSeconds($now);
        $minInterval = max(30, (int) config('bandwidth.min_rate_interval_seconds', 45));
        if ($seconds < $minInterval) {
            return [null, null];
        }

        $deltaDown = max(0, $downloadBytes - (int) $prev->bytes_in);
        $deltaUp = max(0, $uploadBytes - (int) $prev->bytes_out);

        $rateDown = (int) round(($deltaDown * 8) / $seconds);
        $rateUp = (int) round(($deltaUp * 8) / $seconds);

        $maxBps = (int) config('bandwidth.max_sane_rate_bps', 10_000_000_000);
        if ($rateDown > $maxBps || $rateUp > $maxBps) {
            return [null, null];
        }

        return [$rateDown, $rateUp];
    }

    /**
     * @param  array<string, string>  $sources
     */
    private function upsertSessionLog(
        int $tenantId,
        Customer $customer,
        ?MikrotikServer $server,
        ?Device $device,
        string $sessionKey,
        string $username,
        string $framedIp,
        ?string $callerId,
        int $downloadBytes,
        int $uploadBytes,
        ?int $rateDownload,
        ?int $rateUpload,
        Carbon $now,
        array $sources = [],
    ): void {
        $log = PppSessionLog::query()->where('session_key', $sessionKey)->first();

        $metaRates = [
            'rate_download_bps' => $rateDownload,
            'rate_upload_bps' => $rateUpload,
        ];

        if ($log === null) {
            PppSessionLog::query()->create([
                'tenant_id' => $tenantId,
                'customer_id' => $customer->id,
                'mikrotik_server_id' => $server?->id,
                'device_id' => $device?->id,
                'session_key' => $sessionKey,
                'username' => $username,
                'framed_ip' => $framedIp !== '' ? $framedIp : null,
                'caller_id' => $callerId,
                'bytes_in' => $downloadBytes,
                'bytes_out' => $uploadBytes,
                'peak_rate_in_bps' => $rateDownload ?? 0,
                'peak_rate_out_bps' => $rateUpload ?? 0,
                'started_at' => $now,
                'status' => 'active',
                'meta' => array_merge(['sources' => $sources], $metaRates),
            ]);

            return;
        }

        $peakDown = max((int) $log->peak_rate_in_bps, (int) ($rateDownload ?? 0));
        $peakUp = max((int) $log->peak_rate_out_bps, (int) ($rateUpload ?? 0));
        $meta = $log->meta ?? [];
        $meta['sources'] = array_merge($meta['sources'] ?? [], $sources);
        $meta['rate_download_bps'] = $rateDownload;
        $meta['rate_upload_bps'] = $rateUpload;

        $log->forceFill([
            'customer_id' => $customer->id,
            'bytes_in' => $downloadBytes,
            'bytes_out' => $uploadBytes,
            'peak_rate_in_bps' => $peakDown,
            'peak_rate_out_bps' => $peakUp,
            'framed_ip' => $framedIp !== '' ? $framedIp : $log->framed_ip,
            'caller_id' => $callerId ?? $log->caller_id,
            'device_id' => $device?->id ?? $log->device_id,
            'mikrotik_server_id' => $server?->id ?? $log->mikrotik_server_id,
            'status' => 'active',
            'ended_at' => null,
            'meta' => $meta,
        ])->saveQuietly();
    }

    private function rollupDaily(
        Customer $customer,
        int $downloadBytes,
        int $uploadBytes,
        ?int $rateDownload,
        ?int $rateUpload,
        Carbon $now,
    ): void {
        $date = $now->toDateString();
        $daily = BandwidthUsageDaily::query()->firstOrNew([
            'customer_id' => $customer->id,
            'usage_date' => $date,
        ]);

        if (! $daily->exists) {
            $daily->tenant_id = $customer->tenant_id;
            $daily->bytes_in = 0;
            $daily->bytes_out = 0;
            $daily->peak_rate_in_bps = 0;
            $daily->peak_rate_out_bps = 0;
            $daily->online_seconds = 0;
            $daily->session_count = 0;
        }

        $interval = max(1, (int) config('bandwidth.poll_interval_minutes', 5)) * 60;
        if ($rateDownload !== null && $rateDownload > 0) {
            $daily->bytes_in = (int) $daily->bytes_in + (int) round($rateDownload * $interval / 8);
        }
        if ($rateUpload !== null && $rateUpload > 0) {
            $daily->bytes_out = (int) $daily->bytes_out + (int) round($rateUpload * $interval / 8);
        }
        $daily->online_seconds = (int) $daily->online_seconds + $interval;

        $daily->peak_rate_in_bps = max((int) $daily->peak_rate_in_bps, (int) ($rateDownload ?? 0));
        $daily->peak_rate_out_bps = max((int) $daily->peak_rate_out_bps, (int) ($rateUpload ?? 0));
        $daily->session_count = PppSessionLog::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'active')
            ->count();

        $daily->saveQuietly();
    }

    /**
     * @param  list<int>  $onlineCustomerIds
     */
    private function syncCustomerOnlineFlags(int $tenantId, array $onlineCustomerIds): void
    {
        $now = now();

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereNotIn('id', $onlineCustomerIds !== [] ? $onlineCustomerIds : [0])
            ->where('is_ppp_online', true)
            ->update(['is_ppp_online' => false]);

        if ($onlineCustomerIds === []) {
            return;
        }

        Customer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('id', $onlineCustomerIds)
            ->update([
                'is_ppp_online' => true,
                'ppp_last_seen_at' => $now,
            ]);
    }

    /**
     * @param  list<string>  $activeSessionKeys
     */
    private function closeStaleSessions(int $tenantId, array $activeSessionKeys, Carbon $now): int
    {
        $q = PppSessionLog::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active');

        if ($activeSessionKeys !== []) {
            $q->whereNotIn('session_key', array_unique($activeSessionKeys));
        }

        $closed = 0;
        foreach ($q->cursor() as $log) {
            $log->forceFill([
                'status' => 'closed',
                'ended_at' => $now,
            ])->saveQuietly();

            $closed++;
        }

        return $closed;
    }

    public function pruneOldSamples(): void
    {
        $cutoff = now()->subHours((int) config('bandwidth.sample_retention_hours', 72));
        BandwidthSample::query()->where('sampled_at', '<', $cutoff)->delete();

        $wanCutoff = now()->subHours((int) config('bandwidth.wan_sample_retention_hours', 72));
        WanBandwidthSample::query()->where('sampled_at', '<', $wanCutoff)->delete();
    }

    public function collectWanInterfaces(int $tenantId, ?Carbon $now = null): int
    {
        $now = $now ?? now();
        $count = 0;

        $servers = MikrotikServer::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('is_enabled', true)
            ->get();

        foreach ($servers as $server) {
            try {
                $ifaces = $this->mikrotik->fetchWanInterfaceCounters($server);
            } catch (\Throwable $e) {
                Log::warning('bandwidth.wan_fetch_failed', [
                    'server_id' => $server->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($ifaces as $iface) {
                $ifName = $iface['name'];
                $rx = (int) $iface['rx_byte'];
                $tx = (int) $iface['tx_byte'];

                [$rateDown, $rateUp] = $this->computeWanRates($tenantId, $server->id, $ifName, $rx, $tx, $now);

                WanBandwidthSample::query()->create([
                    'tenant_id' => $tenantId,
                    'mikrotik_server_id' => $server->id,
                    'interface_name' => $ifName,
                    'bytes_in' => $rx,
                    'bytes_out' => $tx,
                    'rate_in_bps' => $rateDown,
                    'rate_out_bps' => $rateUp,
                    'sampled_at' => $now,
                ]);
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{0: ?int, 1: ?int}
     */
    private function computeWanRates(int $tenantId, int $serverId, string $ifName, int $rx, int $tx, Carbon $now): array
    {
        $prev = WanBandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('mikrotik_server_id', $serverId)
            ->where('interface_name', $ifName)
            ->orderByDesc('sampled_at')
            ->first();

        if ($prev === null) {
            return [null, null];
        }

        $seconds = max(1, $prev->sampled_at->diffInSeconds($now));
        $minInterval = max(1, (float) config('bandwidth.wan_min_rate_interval_seconds', 1));
        if ($seconds < $minInterval) {
            return [null, null];
        }

        $deltaDown = max(0, $rx - (int) $prev->bytes_in);
        $deltaUp = max(0, $tx - (int) $prev->bytes_out);
        $rateDown = (int) round(($deltaDown * 8) / $seconds);
        $rateUp = (int) round(($deltaUp * 8) / $seconds);
        $maxBps = (int) config('bandwidth.max_sane_rate_bps', 10_000_000_000);

        if ($rateDown > $maxBps || $rateUp > $maxBps) {
            return [null, null];
        }

        return [$rateDown, $rateUp];
    }

    /**
     * @return array{ok: bool, message: string, active_sessions?: int}
     */
    private function safeRadiusPing(): array
    {
        try {
            return app(RadiusAccountingService::class)->ping();
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'active_sessions' => 0];
        }
    }

    /**
     * WAN port throughput per second (all uplink interfaces combined).
     *
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    public static function aggregateWanLiveMbpsPerSecond(int $tenantId, int $minutes = 30, int $maxPoints = 120): array
    {
        $since = now()->subMinutes($minutes);
        $samples = WanBandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->where(fn ($q) => $q->where('rate_in_bps', '>', 0)->orWhere('rate_out_bps', '>', 0))
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rate_in_bps', 'rate_out_bps']);

        $buckets = [];
        foreach ($samples as $s) {
            $key = $s->sampled_at->copy()->startOfSecond()->toDateTimeString();
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['down' => 0, 'up' => 0, 'at' => $s->sampled_at->copy()->startOfSecond()];
            }
            $buckets[$key]['down'] += max(0, (int) $s->rate_in_bps);
            $buckets[$key]['up'] += max(0, (int) $s->rate_out_bps);
        }

        if ($buckets === []) {
            $live = self::currentWanLiveBps($tenantId);
            if ($live['down_bps'] > 0 || $live['up_bps'] > 0) {
                return [
                    'labels' => [now()->format('H:i:s')],
                    'download_mbps' => [round($live['down_bps'] / 1_000_000, 3)],
                    'upload_mbps' => [round($live['up_bps'] / 1_000_000, 3)],
                ];
            }

            return ['labels' => [], 'download_mbps' => [], 'upload_mbps' => []];
        }

        ksort($buckets);
        if (count($buckets) > $maxPoints) {
            $buckets = array_slice($buckets, -$maxPoints, null, true);
        }

        $labels = [];
        $down = [];
        $up = [];
        foreach ($buckets as $data) {
            $labels[] = ($data['at'] ?? now())->format('H:i:s');
            $down[] = round($data['down'] / 1_000_000, 3);
            $up[] = round($data['up'] / 1_000_000, 3);
        }

        return ['labels' => $labels, 'download_mbps' => $down, 'upload_mbps' => $up];
    }

    /**
     * Per MikroTik WAN/uplink interface (Mbps per second) — one series per port.
     *
     * @return array{
     *   labels: list<string>,
     *   series: list<array{
     *     label: string,
     *     server: string,
     *     interface: string,
     *     download_mbps: list<float>,
     *     upload_mbps: list<float>
     *   }>
     * }
     */
    public static function aggregateWanInterfacesMbpsPerSecond(int $tenantId, int $minutes = 30, int $maxPoints = 120): array
    {
        $since = now()->subMinutes($minutes);
        $samples = WanBandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->where(fn ($q) => $q->where('rate_in_bps', '>', 0)->orWhere('rate_out_bps', '>', 0))
            ->with('mikrotikServer:id,name')
            ->orderBy('sampled_at')
            ->get();

        if ($samples->isEmpty()) {
            return ['labels' => [], 'series' => []];
        }

        $timeline = [];
        foreach ($samples as $sample) {
            $timeline[$sample->sampled_at->copy()->startOfSecond()->toDateTimeString()] = true;
        }
        ksort($timeline);
        if (count($timeline) > $maxPoints) {
            $timeline = array_slice($timeline, -$maxPoints, null, true);
        }

        $labels = array_map(
            fn (string $key) => \Illuminate\Support\Carbon::parse($key)->format('H:i:s'),
            array_keys($timeline),
        );
        $timelineKeys = array_keys($timeline);

        $series = [];
        foreach ($samples->groupBy(fn (WanBandwidthSample $s) => $s->mikrotik_server_id.'|'.$s->interface_name) as $group) {
            /** @var WanBandwidthSample $first */
            $first = $group->first();
            $serverName = $first->mikrotikServer?->name ?? 'Router';
            $ifName = $first->interface_name;
            $bySecond = [];
            foreach ($group as $sample) {
                $bySecond[$sample->sampled_at->copy()->startOfSecond()->toDateTimeString()] = $sample;
            }

            $download = [];
            $upload = [];
            foreach ($timelineKeys as $key) {
                $sample = $bySecond[$key] ?? null;
                $download[] = $sample ? round(max(0, (int) $sample->rate_in_bps) / 1_000_000, 3) : 0.0;
                $upload[] = $sample ? round(max(0, (int) $sample->rate_out_bps) / 1_000_000, 3) : 0.0;
            }

            $series[] = [
                'label' => "{$serverName} · {$ifName}",
                'server' => $serverName,
                'interface' => $ifName,
                'download_mbps' => $download,
                'upload_mbps' => $upload,
            ];
        }

        usort($series, fn (array $a, array $b) => strcmp($a['label'], $b['label']));

        return ['labels' => $labels, 'series' => $series];
    }

    /**
     * MikroTik WAN ports vs all PPPoE subscribers — per-interface WAN lines when available.
     *
     * @return array{
     *   labels: list<string>,
     *   wan_series: list<array{label: string, download_mbps: list<float>, upload_mbps: list<float>}>,
     *   wan_download_mbps: list<float>,
     *   wan_upload_mbps: list<float>,
     *   users_download_mbps: list<float>,
     *   users_upload_mbps: list<float>
     * }
     */
    public static function aggregateLiveComparisonChart(int $tenantId, int $minutes = 30, int $maxPoints = 120): array
    {
        $wanIfaces = self::aggregateWanInterfacesMbpsPerSecond($tenantId, $minutes, $maxPoints);
        $users = self::aggregateLiveMbpsPerSecond($tenantId, $minutes, $maxPoints);
        $wanTotal = self::aggregateWanLiveMbpsPerSecond($tenantId, $minutes, $maxPoints);

        $labels = array_values(array_unique(array_merge(
            $wanIfaces['labels'],
            $users['labels'],
            $wanTotal['labels'],
        )));
        sort($labels);
        if (count($labels) > $maxPoints) {
            $labels = array_slice($labels, -$maxPoints);
        }

        if ($labels === []) {
            $w = self::currentWanLiveBps($tenantId);
            $u = self::currentTenantLiveBps($tenantId);
            $label = now()->format('H:i:s');
            $wanSeries = [];
            foreach (self::latestWanInterfaceSnapshots($tenantId) as $snap) {
                $wanSeries[] = [
                    'label' => "{$snap['server']} · {$snap['interface']}",
                    'download_mbps' => [$snap['down_mbps']],
                    'upload_mbps' => [$snap['up_mbps']],
                ];
            }

            return [
                'labels' => [$label],
                'wan_series' => $wanSeries,
                'wan_download_mbps' => [round($w['down_bps'] / 1_000_000, 3)],
                'wan_upload_mbps' => [round($w['up_bps'] / 1_000_000, 3)],
                'users_download_mbps' => [round($u['down_bps'] / 1_000_000, 3)],
                'users_upload_mbps' => [round($u['up_bps'] / 1_000_000, 3)],
            ];
        }

        $userIndex = array_flip($users['labels']);
        $wanIndex = array_flip($wanTotal['labels']);
        $ifaceIndex = array_flip($wanIfaces['labels']);

        $wanD = [];
        $wanU = [];
        $usrD = [];
        $usrU = [];
        foreach ($labels as $label) {
            $ui = $userIndex[$label] ?? null;
            $wi = $wanIndex[$label] ?? null;
            $usrD[] = $ui !== null ? ($users['download_mbps'][$ui] ?? 0) : 0;
            $usrU[] = $ui !== null ? ($users['upload_mbps'][$ui] ?? 0) : 0;
            $wanD[] = $wi !== null ? ($wanTotal['download_mbps'][$wi] ?? 0) : 0;
            $wanU[] = $wi !== null ? ($wanTotal['upload_mbps'][$wi] ?? 0) : 0;
        }

        $wanSeries = [];
        foreach ($wanIfaces['series'] as $iface) {
            $alignedDown = [];
            $alignedUp = [];
            foreach ($labels as $label) {
                $i = $ifaceIndex[$label] ?? null;
                $alignedDown[] = $i !== null ? ($iface['download_mbps'][$i] ?? 0) : 0;
                $alignedUp[] = $i !== null ? ($iface['upload_mbps'][$i] ?? 0) : 0;
            }
            $wanSeries[] = [
                'label' => $iface['label'],
                'download_mbps' => $alignedDown,
                'upload_mbps' => $alignedUp,
            ];
        }

        return [
            'labels' => $labels,
            'wan_series' => $wanSeries,
            'wan_download_mbps' => $wanD,
            'wan_upload_mbps' => $wanU,
            'users_download_mbps' => $usrD,
            'users_upload_mbps' => $usrU,
        ];
    }

    /**
     * @return array{down_bps: int, up_bps: int}
     */
    public static function currentWanLiveBps(int $tenantId): array
    {
        $since = now()->subMinutes(3);
        $samples = WanBandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->whereNotNull('rate_in_bps')
            ->orderByDesc('sampled_at')
            ->get()
            ->groupBy(fn (WanBandwidthSample $s) => $s->mikrotik_server_id.'|'.$s->interface_name)
            ->map(fn ($group) => $group->first());

        return [
            'down_bps' => (int) $samples->sum(fn ($s) => max(0, (int) $s->rate_in_bps)),
            'up_bps' => (int) $samples->sum(fn ($s) => max(0, (int) $s->rate_out_bps)),
        ];
    }

    /**
     * @return list<array{server: string, interface: string, down_mbps: float, up_mbps: float}>
     */
    public static function latestWanInterfaceSnapshots(int $tenantId): array
    {
        $since = now()->subMinutes(5);

        return WanBandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->with('mikrotikServer:id,name')
            ->orderByDesc('sampled_at')
            ->get()
            ->groupBy(fn (WanBandwidthSample $s) => $s->mikrotik_server_id.'|'.$s->interface_name)
            ->map(fn ($group) => $group->first())
            ->values()
            ->map(fn (WanBandwidthSample $s) => [
                'server' => $s->mikrotikServer?->name ?? 'Router',
                'interface' => $s->interface_name,
                'down_mbps' => round(max(0, (int) $s->rate_in_bps) / 1_000_000, 2),
                'up_mbps' => round(max(0, (int) $s->rate_out_bps) / 1_000_000, 2),
            ])
            ->all();
    }

    /**
     * Subscribers (PPPoE users) per second — sum of all session rates each second.
     *
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    public static function aggregateLiveMbpsPerSecond(int $tenantId, int $minutes = 30, int $maxPoints = 120): array
    {
        $since = now()->subMinutes($minutes);
        $samples = BandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->where(fn ($q) => $q->where('rate_in_bps', '>', 0)->orWhere('rate_out_bps', '>', 0))
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rate_in_bps', 'rate_out_bps']);

        $buckets = [];
        foreach ($samples as $s) {
            $key = $s->sampled_at->copy()->startOfSecond()->toDateTimeString();
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['down' => 0, 'up' => 0, 'at' => $s->sampled_at->copy()->startOfSecond()];
            }
            $buckets[$key]['down'] += max(0, (int) $s->rate_in_bps);
            $buckets[$key]['up'] += max(0, (int) $s->rate_out_bps);
        }

        if ($buckets === []) {
            $live = self::currentTenantLiveBps($tenantId);
            if ($live['down_bps'] > 0 || $live['up_bps'] > 0) {
                return [
                    'labels' => [now()->format('H:i:s')],
                    'download_mbps' => [round($live['down_bps'] / 1_000_000, 3)],
                    'upload_mbps' => [round($live['up_bps'] / 1_000_000, 3)],
                ];
            }

            return ['labels' => [], 'download_mbps' => [], 'upload_mbps' => []];
        }

        ksort($buckets);
        if (count($buckets) > $maxPoints) {
            $buckets = array_slice($buckets, -$maxPoints, null, true);
        }

        $labels = [];
        $down = [];
        $up = [];
        foreach ($buckets as $data) {
            $labels[] = ($data['at'] ?? now())->format('H:i:s');
            $down[] = round($data['down'] / 1_000_000, 3);
            $up[] = round($data['up'] / 1_000_000, 3);
        }

        return ['labels' => $labels, 'download_mbps' => $down, 'upload_mbps' => $up];
    }

    /**
     * @return array{down_bps: int, up_bps: int}
     */
    public static function currentTenantLiveBps(int $tenantId): array
    {
        $sessions = PppSessionLog::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->get();

        $down = 0;
        $up = 0;
        foreach ($sessions as $session) {
            $down += max(0, (int) ($session->liveDownloadBps() ?? 0));
            $up += max(0, (int) ($session->liveUploadBps() ?? 0));
        }

        return ['down_bps' => $down, 'up_bps' => $up];
    }

    /**
     * Recent 5-minute buckets for live charts (legacy; prefer aggregateLiveMbpsPerSecond).
     *
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    public static function aggregateRecentMbps(int $tenantId, int $minutes = 120): array
    {
        $since = now()->subMinutes($minutes);
        $samples = BandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->where(fn ($q) => $q->where('rate_in_bps', '>', 0)->orWhere('rate_out_bps', '>', 0))
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rate_in_bps', 'rate_out_bps']);

        $buckets = [];
        foreach ($samples as $s) {
            $key = $s->sampled_at->format('Y-m-d H:i').'-'.(int) floor($s->sampled_at->minute / 5) * 5;
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['down' => [], 'up' => [], 'at' => $s->sampled_at];
            }
            if ($s->rate_in_bps !== null && $s->rate_in_bps > 0) {
                $buckets[$key]['down'][] = (int) $s->rate_in_bps;
            }
            if ($s->rate_out_bps !== null && $s->rate_out_bps > 0) {
                $buckets[$key]['up'][] = (int) $s->rate_out_bps;
            }
        }

        ksort($buckets);
        $labels = [];
        $down = [];
        $up = [];
        foreach ($buckets as $data) {
            $labels[] = ($data['at'] ?? now())->format('H:i');
            $down[] = count($data['down'] ?? []) ? round(array_sum($data['down']) / count($data['down']) / 1_000_000, 2) : 0;
            $up[] = count($data['up'] ?? []) ? round(array_sum($data['up']) / count($data['up']) / 1_000_000, 2) : 0;
        }

        return ['labels' => $labels, 'download_mbps' => $down, 'upload_mbps' => $up];
    }

    public static function aggregateHourlyMbps(int $tenantId, int $hours = 24): array
    {
        $since = now()->subHours($hours);
        $samples = BandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('sampled_at', '>=', $since)
            ->orderBy('sampled_at')
            ->get(['sampled_at', 'rate_in_bps', 'rate_out_bps']);

        $buckets = [];
        foreach ($samples as $s) {
            $key = $s->sampled_at->format('Y-m-d H:00');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['down' => [], 'up' => []];
            }
            if ($s->rate_in_bps !== null && $s->rate_in_bps > 0) {
                $buckets[$key]['down'][] = (int) $s->rate_in_bps;
            }
            if ($s->rate_out_bps !== null && $s->rate_out_bps > 0) {
                $buckets[$key]['up'][] = (int) $s->rate_out_bps;
            }
        }

        $labels = [];
        $down = [];
        $up = [];
        foreach ($buckets as $hour => $data) {
            $labels[] = Carbon::parse($hour)->format('M j H:i');
            $down[] = count($data['down'] ?? []) ? round(array_sum($data['down']) / count($data['down']) / 1000000, 2) : 0;
            $up[] = count($data['up'] ?? []) ? round(array_sum($data['up']) / count($data['up']) / 1000000, 2) : 0;
        }

        return ['labels' => $labels, 'download_mbps' => $down, 'upload_mbps' => $up];
    }

    /**
     * Per-subscriber minute buckets for live traffic charts (RX = download, TX = upload).
     *
     * @return array{labels: list<string>, download_mbps: list<float>, upload_mbps: list<float>}
     */
    public static function aggregateSubscriberMbps(int $tenantId, int $customerId, int $minutes = 90): array
    {
        $since = now()->subMinutes($minutes);
        $samples = BandwidthSample::query()
            ->where('tenant_id', $tenantId)
            ->where('customer_id', $customerId)
            ->where('sampled_at', '>=', $since)
            ->orderByDesc('sampled_at')
            ->limit(500)
            ->get(['sampled_at', 'rate_in_bps', 'rate_out_bps'])
            ->reverse()
            ->values();

        $buckets = [];
        foreach ($samples as $s) {
            $key = $s->sampled_at->format('Y-m-d H:i');
            if (! isset($buckets[$key])) {
                $buckets[$key] = ['down' => [], 'up' => [], 'at' => $s->sampled_at];
            }
            if ($s->rate_in_bps !== null && $s->rate_in_bps >= 0) {
                $buckets[$key]['down'][] = (int) $s->rate_in_bps;
            }
            if ($s->rate_out_bps !== null && $s->rate_out_bps >= 0) {
                $buckets[$key]['up'][] = (int) $s->rate_out_bps;
            }
        }

        ksort($buckets);
        $labels = [];
        $down = [];
        $up = [];
        foreach ($buckets as $data) {
            $labels[] = ($data['at'] ?? now())->format('H:i');
            $down[] = count($data['down'] ?? [])
                ? round(array_sum($data['down']) / count($data['down']) / 1_000_000, 3)
                : 0;
            $up[] = count($data['up'] ?? [])
                ? round(array_sum($data['up']) / count($data['up']) / 1_000_000, 3)
                : 0;
        }

        return ['labels' => $labels, 'download_mbps' => $down, 'upload_mbps' => $up];
    }

    /**
     * @return array{rx_bps: ?int, tx_bps: ?int}
     */
    public static function currentSubscriberRates(int $customerId): array
    {
        $session = PppSessionLog::query()
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->whereNull('ended_at')
            ->orderByDesc('id')
            ->first();

        if ($session !== null) {
            return [
                'rx_bps' => $session->liveDownloadBps(),
                'tx_bps' => $session->liveUploadBps(),
            ];
        }

        $latest = BandwidthSample::query()
            ->where('customer_id', $customerId)
            ->orderByDesc('sampled_at')
            ->first(['rate_in_bps', 'rate_out_bps']);

        return [
            'rx_bps' => $latest?->rate_in_bps,
            'tx_bps' => $latest?->rate_out_bps,
        ];
    }
}
