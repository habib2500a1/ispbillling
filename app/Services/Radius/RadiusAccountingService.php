<?php

namespace App\Services\Radius;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RadiusAccountingService
{
    public function isEnabled(): bool
    {
        return (bool) config('radius.accounting_enabled', false);
    }

    /**
     * Active sessions from radacct (acctstoptime IS NULL).
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
     *   acctupdatetime: string|null
     * }>
     */
    public function fetchActiveSessions(): array
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
                ->limit(10000)
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

    public function ping(): array
    {
        if (! $this->isEnabled()) {
            return ['ok' => false, 'message' => 'RADIUS accounting disabled in .env'];
        }

        try {
            $table = (string) config('radius.radacct_table', 'radacct');
            $active = DB::connection('radius')->table($table)->whereNull('acctstoptime')->count();

            return ['ok' => true, 'message' => 'Connected', 'active_sessions' => $active];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage(), 'active_sessions' => 0];
        }
    }
}
