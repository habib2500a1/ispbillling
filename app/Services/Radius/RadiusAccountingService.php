<?php

namespace App\Services\Radius;

use App\Support\CustomerPppLoginResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RadiusAccountingService
{
    public function __construct(
        private readonly RadiusNasResolver $nasResolver,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('radius.accounting_enabled', false);
    }

    /**
     * Active sessions from radacct (acctstoptime IS NULL), tenant-scoped.
     *
     * @return list<array{
     *   acctsessionid: string,
     *   username: string,
     *   nasipaddress: string,
     *   framedipaddress: string,
     *   callingstationid: string,
     *   bytes_in: int,
     *   bytes_out: int,
     *   acctstarttime: string|null,
     *   acctupdatetime: string|null,
     *   mikrotik_server_id: int|null
     * }>
     */
    public function fetchActiveSessionsForTenant(int $tenantId): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        $loginIndex = CustomerPppLoginResolver::indexForTenant($tenantId);
        if ($loginIndex === []) {
            return [];
        }

        $nasIps = $this->nasResolver->nasIpsForTenant($tenantId);
        $nasMap = $this->nasResolver->nasMapForTenant($tenantId);
        $staleBefore = now()->subSeconds(max(60, (int) config('radius.interim_interval', 300) * 2));

        try {
            $table = (string) config('radius.radacct_table', 'radacct');
            $query = DB::connection('radius')
                ->table($table)
                ->whereNull('acctstoptime')
                ->where(function ($q) use ($staleBefore): void {
                    $q->whereNull('acctupdatetime')
                        ->orWhere('acctupdatetime', '>=', $staleBefore);
                });

            if ($nasIps !== []) {
                $query->whereIn('nasipaddress', $nasIps);
            }

            $rows = $query
                ->orderByDesc('acctupdatetime')
                ->limit((int) config('radius.max_active_sessions', 10000))
                ->get([
                    'acctsessionid',
                    'username',
                    'nasipaddress',
                    'framedipaddress',
                    'callingstationid',
                    'acctinputoctets',
                    'acctoutputoctets',
                    'acctstarttime',
                    'acctupdatetime',
                ]);

            $sessions = [];
            foreach ($rows as $row) {
                $username = trim((string) ($row->username ?? ''));
                if ($username === '') {
                    continue;
                }

                $loginKey = CustomerPppLoginResolver::normalize($username);
                if (! isset($loginIndex[$loginKey])) {
                    continue;
                }

                $nasIp = (string) ($row->nasipaddress ?? '');
                $serverId = $nasMap[$this->normalizeNasIp($nasIp)] ?? null;

                $sessions[] = [
                    'acctsessionid' => (string) ($row->acctsessionid ?? ''),
                    'username' => $username,
                    'nasipaddress' => $nasIp,
                    'framedipaddress' => (string) ($row->framedipaddress ?? ''),
                    'callingstationid' => (string) ($row->callingstationid ?? ''),
                    'bytes_in' => (int) ($row->acctinputoctets ?? 0),
                    'bytes_out' => (int) ($row->acctoutputoctets ?? 0),
                    'acctstarttime' => $row->acctstarttime ? (string) $row->acctstarttime : null,
                    'acctupdatetime' => $row->acctupdatetime ? (string) $row->acctupdatetime : null,
                    'mikrotik_server_id' => $serverId,
                ];
            }

            return $sessions;
        } catch (\Throwable $e) {
            Log::warning('radius.accounting.fetch_failed', [
                'tenant_id' => $tenantId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @deprecated Use fetchActiveSessionsForTenant() for multi-tenant safety.
     *
     * @return list<array<string, mixed>>
     */
    public function fetchActiveSessions(): array
    {
        if (! config('radius.allow_global_fetch', false)) {
            return [];
        }

        return $this->fetchActiveSessionsUnscoped();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fetchActiveSessionsUnscoped(): array
    {
        if (! $this->isEnabled()) {
            return [];
        }

        try {
            $table = (string) config('radius.radacct_table', 'radacct');
            $rows = DB::connection('radius')
                ->table($table)
                ->whereNull('acctstoptime')
                ->orderByDesc('acctupdatetime')
                ->limit((int) config('radius.max_active_sessions', 10000))
                ->get([
                    'acctsessionid',
                    'username',
                    'nasipaddress',
                    'framedipaddress',
                    'callingstationid',
                    'acctinputoctets',
                    'acctoutputoctets',
                    'acctstarttime',
                    'acctupdatetime',
                ]);

            $sessions = [];
            foreach ($rows as $row) {
                $username = trim((string) ($row->username ?? ''));
                if ($username === '') {
                    continue;
                }
                $sessions[] = [
                    'acctsessionid' => (string) ($row->acctsessionid ?? ''),
                    'username' => $username,
                    'nasipaddress' => (string) ($row->nasipaddress ?? ''),
                    'framedipaddress' => (string) ($row->framedipaddress ?? ''),
                    'callingstationid' => (string) ($row->callingstationid ?? ''),
                    'bytes_in' => (int) ($row->acctinputoctets ?? 0),
                    'bytes_out' => (int) ($row->acctoutputoctets ?? 0),
                    'acctstarttime' => $row->acctstarttime ? (string) $row->acctstarttime : null,
                    'acctupdatetime' => $row->acctupdatetime ? (string) $row->acctupdatetime : null,
                    'mikrotik_server_id' => null,
                ];
            }

            return $sessions;
        } catch (\Throwable $e) {
            Log::warning('radius.accounting.fetch_failed', ['error' => $e->getMessage()]);

            return [];
        }
    }

    public function sessionKey(string $acctSessionId): string
    {
        return 'rad-'.($acctSessionId !== '' ? $acctSessionId : md5((string) microtime(true)));
    }

    public function ping(?int $tenantId = null): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'RADIUS accounting disabled'];
        }

        try {
            $table = (string) config('radius.radacct_table', 'radacct');
            $active = DB::connection('radius')->table($table)->whereNull('acctstoptime')->count();
            $tenantScoped = $tenantId !== null
                ? count($this->fetchActiveSessionsForTenant($tenantId))
                : null;

            return [
                'ok' => true,
                'message' => 'Connected',
                'active_sessions' => $active,
                'tenant_active_sessions' => $tenantScoped,
            ];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'active_sessions' => 0];
        }
    }

    private function normalizeNasIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return '';
        }

        if (str_contains($ip, ':') && ! str_contains($ip, '::')) {
            $ip = explode(':', $ip)[0];
        }

        return $ip;
    }
}
