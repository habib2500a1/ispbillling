<?php

namespace App\Services\Mikrotik;

use App\Models\MikrotikServer;
use App\Support\CustomerPppLoginResolver;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Runs sync operations across every enabled MikroTik router for a tenant.
 * Use this for multi-router ISPs (2+ connections) so all code paths stay consistent.
 */
final class MikrotikFleetCoordinator
{
    public function __construct(
        private readonly MikrotikServerService $mikrotik,
        private readonly MikrotikPppImportService $import,
    ) {}

    /**
     * @return Collection<int, MikrotikServer>
     */
    public function enabledServers(?int $tenantId = null, ?int $onlyServerId = null): Collection
    {
        $query = MikrotikServer::query()
            ->withoutGlobalScopes()
            ->where('is_enabled', true)
            ->orderBy('id');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        if ($onlyServerId !== null) {
            $query->whereKey($onlyServerId);
        }

        return $query->get();
    }

    /**
     * @template T
     *
     * @param  callable(MikrotikServer): T  $callback
     * @return list<array{server: MikrotikServer, result: T|null, error: ?string}>
     */
    public function mapServers(?int $tenantId, ?int $onlyServerId, callable $callback): array
    {
        $out = [];

        foreach ($this->enabledServers($tenantId, $onlyServerId) as $server) {
            try {
                $out[] = [
                    'server' => $server,
                    'result' => $callback($server),
                    'error' => null,
                ];
            } catch (\Throwable $e) {
                Log::warning('mikrotik.fleet.server_failed', [
                    'server_id' => $server->id,
                    'host' => $server->host,
                    'message' => $e->getMessage(),
                ]);
                $out[] = [
                    'server' => $server,
                    'result' => null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $out;
    }

    /**
     * @return array{
     *   servers: list<array<string, mixed>>,
     *   sessions: list<array<string, mixed>>,
     *   api_ok: bool,
     *   errors: list<string>
     * }
     */
    public function collectActiveSessionsForTenant(int $tenantId): array
    {
        $sessions = [];
        $errors = [];
        $anySuccess = false;
        $serverRows = [];

        foreach ($this->enabledServers($tenantId) as $server) {
            $fetch = $this->mikrotik->fetchActivePppSessions($server);
            $serverRows[] = [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'status' => $server->last_api_status,
                'sessions' => count($fetch['sessions']),
                'error' => $fetch['error'],
            ];

            if ($fetch['error'] !== null) {
                $errors[] = "{$server->name} ({$server->host}): {$fetch['error']}";

                continue;
            }

            $anySuccess = true;

            foreach ($fetch['sessions'] as $row) {
                $username = trim((string) ($row['name'] ?? ''));
                if ($username === '') {
                    continue;
                }

                $routerId = (string) ($row['router_id'] ?? '');
                $counters = \App\Support\BandwidthDirection::fromMikrotikCounters(
                    (int) ($row['bytes_in'] ?? 0),
                    (int) ($row['bytes_out'] ?? 0),
                );

                $sessions[] = [
                    'username' => $username,
                    'session_key' => $this->mikrotik->sessionKey($server, $routerId !== '' ? $routerId : $username),
                    'mikrotik_server_id' => $server->id,
                    'mikrotik_server_name' => $server->name,
                    'framed_ip' => (string) ($row['address'] ?? ''),
                    'caller_id' => $this->normalizeMac((string) ($row['caller_id'] ?? '')),
                    'download_bytes' => $counters['download_bytes'],
                    'upload_bytes' => $counters['upload_bytes'],
                    'instant_rate_download_bps' => $row['rate_download_bps'] ?? null,
                    'instant_rate_upload_bps' => $row['rate_upload_bps'] ?? null,
                    'sources' => ['api' => now()->toIso8601String(), 'server_id' => $server->id],
                ];
            }
        }

        return [
            'servers' => $serverRows,
            'sessions' => $sessions,
            'api_ok' => $anySuccess,
            'errors' => $errors,
        ];
    }

    /**
     * @return array{created: int, updated: int, skipped: int, errors: list<string>, by_server: list<array<string, mixed>>}
     */
    public function importAllServers(
        ?int $tenantId = null,
        ?int $onlyServerId = null,
        array $options = [],
    ): array {
        $totals = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => [], 'by_server' => []];

        foreach ($this->enabledServers($tenantId, $onlyServerId) as $server) {
            $result = $this->import->importFromRouter($server, $options);
            $totals['created'] += $result['created'];
            $totals['updated'] += $result['updated'];
            $totals['skipped'] += $result['skipped'];
            $totals['errors'] = array_merge($totals['errors'], $result['errors']);
            $totals['by_server'][] = [
                'server_id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'created' => $result['created'],
                'updated' => $result['updated'],
                'skipped' => $result['skipped'],
            ];
        }

        return $totals;
    }

    /**
     * @return array{polled: int, online: int, offline: int, servers: list<array<string, mixed>>}
     */
    public function probeAllServers(?int $tenantId = null, ?int $onlyServerId = null): array
    {
        $stats = ['polled' => 0, 'online' => 0, 'offline' => 0, 'servers' => []];

        foreach ($this->enabledServers($tenantId, $onlyServerId) as $server) {
            $this->mikrotik->probeAndPersist($server);
            $server = $server->fresh() ?? $server;
            $stats['polled']++;
            if ($server->last_api_status === 'online') {
                $stats['online']++;
            } else {
                $stats['offline']++;
            }
            $stats['servers'][] = [
                'id' => $server->id,
                'name' => $server->name,
                'host' => $server->host,
                'status' => $server->last_api_status,
                'error' => $server->last_error,
            ];
        }

        return $stats;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function fleetSummary(?int $tenantId = null): array
    {
        return $this->enabledServers($tenantId)
            ->map(fn (MikrotikServer $s): array => [
                'id' => $s->id,
                'name' => $s->name,
                'host' => $s->host,
                'status' => $s->last_api_status,
                'last_checked_at' => $s->last_checked_at?->toIso8601String(),
                'subscribers' => $s->tenant_id
                    ? \App\Models\Customer::withoutGlobalScopes()
                        ->where('tenant_id', $s->tenant_id)
                        ->where('mikrotik_server_id', $s->id)
                        ->count()
                    : 0,
            ])
            ->values()
            ->all();
    }

    /**
     * Servers used for PPP push (suspend/unsuspend) for a subscriber.
     *
     * @return Collection<int, MikrotikServer>
     */
    public function serversForCustomer(\App\Models\Customer $customer): Collection
    {
        $tenantId = (int) $customer->tenant_id;

        if (config('mikrotik.provision_assigned_server_only', true) && $customer->mikrotik_server_id) {
            $server = MikrotikServer::query()
                ->withoutGlobalScopes()
                ->whereKey($customer->mikrotik_server_id)
                ->where('is_enabled', true)
                ->first();

            if ($server !== null) {
                return collect([$server]);
            }

            // Assigned router missing/disabled — fall back so Net ON / paid sync is not silent no-op.
            Log::warning('mikrotik.fleet.assigned_server_unavailable', [
                'customer_id' => $customer->id,
                'mikrotik_server_id' => $customer->mikrotik_server_id,
            ]);
        }

        return $this->enabledServers($tenantId);
    }

    private function normalizeMac(string $mac): ?string
    {
        $mac = strtoupper(preg_replace('/[^A-Fa-f0-9]/', '', $mac) ?? '');

        return strlen($mac) >= 12 ? $mac : null;
    }
}
