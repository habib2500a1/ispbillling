<?php

namespace App\Services\Olt;

use App\Http\Controllers\MikrotikController;
use App\Models\CustomerOnu;
use App\Models\CustomersInfo;
use App\Models\Olt;
use App\Support\SnmpClient;
use Throwable;

/**
 * Link a subscriber ONU from this panel's OLT (SNMP) and/or live MikroTik session MAC.
 *
 * BDCOM P3600: PPPoE caller-id is usually the LAN MAC, not the ONU PON MAC.
 * Match FDB (Q-BRIDGE) → ifIndex → ifName + optical (0.1 dBm).
 */
final class LocalOltOnuSyncService
{
    private const IF_NAME = '1.3.6.1.2.1.31.1.1.1.1';

    private const IF_ALIAS = '1.3.6.1.2.1.31.1.1.1.18';

    private const FDB_PORT = '1.3.6.1.2.1.17.7.1.2.2.1.2';

    private const BRIDGE_IF = '1.3.6.1.2.1.17.1.4.1.2';

    /** @var array<string, string> */
    private const BDCOM = [
        'mac' => '1.3.6.1.4.1.3320.101.10.1.1.3',
        'desc' => '1.3.6.1.4.1.3320.101.10.1.1.2',
        'rx' => '1.3.6.1.4.1.3320.101.10.5.1.5',
        'tx' => '1.3.6.1.4.1.3320.101.10.5.1.6',
    ];

    private const WALK_TIMEOUT_US = 12_000_000;

    /**
     * @return array{onu: ?CustomerOnu, message: string, ok: bool}
     */
    public function syncForCustomer(CustomersInfo $customer): array
    {
        $customer->loadMissing('pppUser');
        $username = (string) ($customer->pppUser?->username ?? '');
        $router = (string) ($customer->pppUser?->router_name ?? '');

        $session = $this->liveSession($router, $username);
        $sessionMac = $session['mac'] ?? $this->normalizeMac((string) ($customer->pppUser?->caller_id ?? ''));
        $notes = [];

        if ($session['mac'] ?? null) {
            $notes[] = 'MikroTik session MAC '.$session['mac'];
            if ($customer->pppUser && $customer->pppUser->caller_id !== $session['mac']) {
                $customer->pppUser->caller_id = $session['mac'];
            }
        }
        if (($session['ip'] ?? null) && $customer->pppUser) {
            $customer->pppUser->ppp_remote_ip = $session['ip'];
            $notes[] = 'IP '.$session['ip'];
        }
        if ($customer->pppUser?->isDirty()) {
            $customer->pppUser->save();
        }

        $storedMac = $this->normalizeMac((string) ($customer->primaryOnu()?->mac_address ?? ''));
        $oltHit = $this->matchOnOlt($sessionMac, $username, $storedMac);
        if ($oltHit) {
            $notes[] = 'OLT '.$oltHit['olt_name'].($oltHit['pon'] ? ' '.$oltHit['pon'] : '');
            if ($oltHit['rx'] !== null) {
                $notes[] = 'RX '.$oltHit['rx'].' dBm';
            }
            if ($oltHit['tx'] !== null) {
                $notes[] = 'TX '.$oltHit['tx'].' dBm';
            }
        }

        if (! $sessionMac && ! $oltHit) {
            $oltCount = Olt::query()->where('status', 'active')->count();

            return [
                'onu' => null,
                'ok' => false,
                'message' => $oltCount > 0
                    ? __('No ONU match yet. OLT SNMP did not return this user — enter MAC and PON, then Save optical.')
                    : __('Add an OLT first, or enter MAC / PON and Save optical.'),
            ];
        }

        $olt = $oltHit
            ? Olt::query()->find($oltHit['olt_id'])
            : Olt::query()->where('status', 'active')->orderBy('id')->first();

        $onu = $customer->primaryOnu() ?? new CustomerOnu(['customers_info_id' => $customer->id]);
        $onu->fill([
            'customers_info_id' => $customer->id,
            'olt_id' => $olt?->id,
            'olt_name' => $oltHit['olt_name'] ?? $olt?->name,
            'pon_port' => $oltHit['pon'] ?? $onu->pon_port,
            'mac_address' => $sessionMac ?? $oltHit['mac'] ?? $onu->mac_address,
            'serial_number' => $oltHit['onu_mac'] ?? $oltHit['serial'] ?? $onu->serial_number,
            'rx_power_dbm' => $oltHit['rx'] ?? $onu->rx_power_dbm,
            'tx_power_dbm' => $oltHit['tx'] ?? $onu->tx_power_dbm,
            'oper_status' => $oltHit['status'] ?? ($sessionMac ? 'online' : $onu->oper_status),
            'source' => $oltHit ? 'snmp' : 'mikrotik',
            'last_polled_at' => now(),
        ]);
        $onu->save();
        app(OpticalRxHistoryService::class)->record($onu, $onu->source);

        return [
            'onu' => $onu,
            'ok' => true,
            'message' => __('ONU linked').($notes !== [] ? ' — '.implode(', ', $notes) : ''),
        ];
    }

    /**
     * @return array{mac: ?string, ip: ?string}
     */
    private function liveSession(string $router, string $username): array
    {
        $out = ['mac' => null, 'ip' => null];
        if ($router === '' || $username === '') {
            return $out;
        }

        try {
            $rows = app(MikrotikController::class)->getActivePppSessions($router);
            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? $row['user'] ?? '');
                if (strcasecmp($name, $username) !== 0) {
                    continue;
                }
                $out['mac'] = $this->normalizeMac((string) ($row['caller-id'] ?? $row['caller_id'] ?? ''));
                $ip = trim((string) ($row['address'] ?? $row['remote-address'] ?? $row['radius'] ?? ''));
                if (str_contains($ip, '/')) {
                    $ip = explode('/', $ip, 2)[0];
                }
                $out['ip'] = filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;

                return $out;
            }
        } catch (Throwable) {
            return $out;
        }

        return $out;
    }

    /**
     * @return array{olt_id: int, olt_name: string, mac: ?string, onu_mac: ?string, pon: ?string, serial: ?string, rx: ?float, tx: ?float, status: ?string}|null
     */
    private function matchOnOlt(?string $sessionMac, string $username, ?string $storedMac): ?array
    {
        if (! SnmpClient::available()) {
            return null;
        }

        $olts = Olt::query()->where('status', 'active')->orderBy('id')->get();
        $probe = app(OltSnmpProbeService::class);

        foreach ($olts as $olt) {
            try {
                $hit = $this->matchOnOneOlt($olt, $probe, $sessionMac, $username, $storedMac);
                if ($hit) {
                    return $hit;
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @return array{olt_id: int, olt_name: string, mac: ?string, onu_mac: ?string, pon: ?string, serial: ?string, rx: ?float, tx: ?float, status: ?string}|null
     */
    private function matchOnOneOlt(Olt $olt, OltSnmpProbeService $probe, ?string $sessionMac, string $username, ?string $storedMac): ?array
    {
        $peer = $probe->snmpPeer($olt);
        $community = $probe->effectiveCommunity($olt);
        $ifNames = $this->walkIfNames($peer, $community);

        $ifIndex = $this->ifIndexFromFdb($peer, $community, array_filter([$sessionMac, $storedMac]))
            ?? $this->ifIndexFromOnuMac($peer, $community, array_filter([$sessionMac, $storedMac]))
            ?? $this->ifIndexFromAlias($peer, $community, $username);

        if ($ifIndex === null) {
            return null;
        }

        $pon = $ifNames[$ifIndex] ?? null;
        $onuMac = $this->normalizeMac((string) (SnmpClient::get($peer, $community, self::BDCOM['mac'].'.'.$ifIndex, 4_000_000, 1) ?? ''));
        $desc = SnmpClient::get($peer, $community, self::BDCOM['desc'].'.'.$ifIndex, 4_000_000, 0);
        $rx = $this->parsePower(SnmpClient::get($peer, $community, self::BDCOM['rx'].'.'.$ifIndex, 4_000_000, 1));
        $tx = $this->parsePower(SnmpClient::get($peer, $community, self::BDCOM['tx'].'.'.$ifIndex, 4_000_000, 1));

        return [
            'olt_id' => (int) $olt->id,
            'olt_name' => (string) $olt->name,
            'mac' => $sessionMac ?? $onuMac,
            'onu_mac' => $onuMac,
            'pon' => $pon,
            'serial' => $desc ? trim($desc) : $onuMac,
            'rx' => $rx,
            'tx' => $tx,
            'status' => 'online',
        ];
    }

    /**
     * @return array<int, string>
     */
    private function walkIfNames(string $peer, string $community): array
    {
        $walk = SnmpClient::realWalkUnchecked($peer, $community, self::IF_NAME, self::WALK_TIMEOUT_US, 1);
        $out = [];
        foreach ($walk as $oidKey => $name) {
            $idx = $this->indexFromOid((string) $oidKey);
            if ($idx !== null) {
                $out[$idx] = trim((string) $name);
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $macs
     */
    private function ifIndexFromFdb(string $peer, string $community, array $macs): ?int
    {
        $wanted = [];
        foreach ($macs as $mac) {
            $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', $mac) ?? '');
            if (strlen($hex) >= 12) {
                $wanted[substr($hex, 0, 12)] = true;
            }
        }
        if ($wanted === []) {
            return null;
        }

        $fdb = SnmpClient::realWalkUnchecked($peer, $community, self::FDB_PORT, self::WALK_TIMEOUT_US, 1);
        foreach ($fdb as $oidKey => $portRaw) {
            $mac = $this->macFromFdbOid((string) $oidKey);
            if ($mac === null || ! isset($wanted[$mac])) {
                continue;
            }
            $port = (int) preg_replace('/\D/', '', (string) $portRaw);
            if ($port <= 0) {
                continue;
            }
            $mapped = SnmpClient::get($peer, $community, self::BRIDGE_IF.'.'.$port, 3_000_000, 0);
            $ifIndex = is_numeric($mapped) ? (int) $mapped : $port;

            return $ifIndex > 0 ? $ifIndex : null;
        }

        return null;
    }

    /**
     * @param  list<string>  $macs
     */
    private function ifIndexFromOnuMac(string $peer, string $community, array $macs): ?int
    {
        $wanted = [];
        foreach ($macs as $mac) {
            $hex = $this->normalizeMac($mac);
            if ($hex) {
                $wanted[strtoupper(preg_replace('/[^0-9A-F]/', '', $hex) ?? '')] = true;
            }
        }
        if ($wanted === []) {
            return null;
        }

        $walk = SnmpClient::realWalkUnchecked($peer, $community, self::BDCOM['mac'], self::WALK_TIMEOUT_US, 1);
        foreach ($walk as $oidKey => $raw) {
            $onuMac = $this->normalizeMac((string) $raw);
            $hex = $onuMac ? strtoupper(preg_replace('/[^0-9A-F]/', '', $onuMac) ?? '') : '';
            if ($hex === '' || ! isset($wanted[$hex])) {
                continue;
            }

            return $this->indexFromOid((string) $oidKey);
        }

        return null;
    }

    private function ifIndexFromAlias(string $peer, string $community, string $username): ?int
    {
        if ($username === '') {
            return null;
        }

        $walk = SnmpClient::realWalkUnchecked($peer, $community, self::IF_ALIAS, self::WALK_TIMEOUT_US, 1);
        foreach ($walk as $oidKey => $alias) {
            if (stripos((string) $alias, $username) === false) {
                continue;
            }

            return $this->indexFromOid((string) $oidKey);
        }

        return null;
    }

    private function macFromFdbOid(string $oidKey): ?string
    {
        $normalized = ltrim($oidKey, '.');
        if (! preg_match('/(?:^|\.)(\d{1,3}(?:\.\d{1,3}){5})$/', $normalized, $m)) {
            return null;
        }
        $parts = explode('.', $m[1]);
        if (count($parts) !== 6) {
            return null;
        }
        $hex = '';
        foreach ($parts as $oct) {
            $n = (int) $oct;
            if ($n < 0 || $n > 255) {
                return null;
            }
            $hex .= sprintf('%02X', $n);
        }

        return $hex;
    }

    private function indexFromOid(string $oidKey): ?int
    {
        if (preg_match('/\.(\d+)$/', ltrim($oidKey, '.'), $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function normalizeMac(string $raw): ?string
    {
        if ($raw === '') {
            return null;
        }
        if (strlen($raw) === 6 && ! ctype_print($raw)) {
            $raw = bin2hex($raw);
        }
        $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', $raw) ?? '');
        if (strlen($hex) < 12 && $raw !== '') {
            $bytes = array_values(array_filter(explode(' ', preg_replace('/[^0-9A-Fa-f ]/', ' ', $raw) ?? '')));
            if (count($bytes) >= 6) {
                $hex = strtoupper(implode('', array_slice($bytes, 0, 6)));
            }
        }
        if (strlen($hex) === 12 && ctype_xdigit($hex)) {
            return implode(':', str_split($hex, 2));
        }
        if (strlen($hex) === 12) {
            return implode(':', str_split($hex, 2));
        }

        return strlen($hex) >= 12 ? implode(':', str_split(substr($hex, 0, 12), 2)) : null;
    }

    private function parsePower(?string $raw): ?float
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (! is_numeric($raw) && preg_match('/-?\d+(?:\.\d+)?/', $raw, $m)) {
            $raw = $m[0];
        }
        if (! is_numeric($raw)) {
            return null;
        }
        $n = (float) $raw;
        if (abs($n) >= 40) {
            $n /= 10;
        } elseif ($n > 0 && abs($n - (int) $n) < 0.0001) {
            $n /= 10;
        }
        if ($n < -40 || $n > 15) {
            return null;
        }

        return round($n, 3);
    }
}
