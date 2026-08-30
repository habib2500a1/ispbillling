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
 */
final class LocalOltOnuSyncService
{
    /** @var array<string, string> */
    private const BDCOM_OIDS = [
        'mac' => '10.20.0.3.4.1.1.3',
        'desc' => '10.20.0.3.4.1.1.2',
        'status' => '10.20.0.3.4.1.1.4',
        'rx' => '10.20.0.3.4.1.5.5',
        'tx' => '10.20.0.3.4.1.5.6',
    ];

    /**
     * @return array{onu: ?CustomerOnu, message: string, ok: bool}
     */
    public function syncForCustomer(CustomersInfo $customer): array
    {
        $customer->loadMissing('pppUser');
        $username = (string) ($customer->pppUser?->username ?? '');
        $router = (string) ($customer->pppUser?->router_name ?? '');

        $sessionMac = $this->liveSessionMac($router, $username);
        $notes = [];
        if ($sessionMac) {
            $notes[] = 'MikroTik session MAC '.$sessionMac;
            if ($customer->pppUser && $customer->pppUser->caller_id !== $sessionMac) {
                $customer->pppUser->caller_id = $sessionMac;
                $customer->pppUser->save();
            }
        }

        $oltHit = $this->matchOnOlt($sessionMac, $username);
        if ($oltHit) {
            $notes[] = 'OLT '.$oltHit['olt_name'];
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
            'mac_address' => $oltHit['mac'] ?? $sessionMac ?? $onu->mac_address,
            'serial_number' => $oltHit['serial'] ?? $onu->serial_number,
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
            'message' => __('ONU linked').( $notes !== [] ? ' — '.implode(', ', $notes) : ''),
        ];
    }

    private function liveSessionMac(string $router, string $username): ?string
    {
        if ($router === '' || $username === '') {
            return null;
        }

        try {
            $rows = app(MikrotikController::class)->getActivePppSessions($router);
            foreach ($rows as $row) {
                $name = (string) ($row['name'] ?? $row['user'] ?? '');
                if (strcasecmp($name, $username) !== 0) {
                    continue;
                }
                $mac = $this->normalizeMac((string) ($row['caller-id'] ?? $row['caller_id'] ?? ''));
                if ($mac) {
                    return $mac;
                }
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    /**
     * @return array{olt_id: int, olt_name: string, mac: ?string, pon: ?string, serial: ?string, rx: ?float, tx: ?float, status: ?string}|null
     */
    private function matchOnOlt(?string $mac, string $username): ?array
    {
        if (! SnmpClient::available()) {
            return null;
        }

        $olts = Olt::query()->where('status', 'active')->orderBy('id')->get();
        $probe = app(OltSnmpProbeService::class);

        foreach ($olts as $olt) {
            try {
                $peer = $probe->snmpPeer($olt);
                $community = $probe->effectiveCommunity($olt);
                $macs = SnmpClient::realWalkUnchecked($peer, $community, self::BDCOM_OIDS['mac']);
                if ($macs === []) {
                    continue;
                }
                $descs = SnmpClient::realWalkUnchecked($peer, $community, self::BDCOM_OIDS['desc']);
                $rxs = SnmpClient::realWalkUnchecked($peer, $community, self::BDCOM_OIDS['rx']);
                $txs = SnmpClient::realWalkUnchecked($peer, $community, self::BDCOM_OIDS['tx']);
                $sts = SnmpClient::realWalkUnchecked($peer, $community, self::BDCOM_OIDS['status']);

                foreach ($macs as $oidKey => $rawMac) {
                    $suffix = SnmpClient::suffixFromOidKey((string) $oidKey, self::BDCOM_OIDS['mac']) ?? '';
                    $onuMac = $this->normalizeMac((string) $rawMac);
                    $desc = $this->walkValue($descs, $suffix, self::BDCOM_OIDS['desc']);
                    $matched = ($mac && $onuMac && strcasecmp($mac, $onuMac) === 0)
                        || ($username !== '' && $desc !== null && stripos($desc, $username) !== false);
                    if (! $matched) {
                        continue;
                    }

                    return [
                        'olt_id' => (int) $olt->id,
                        'olt_name' => (string) $olt->name,
                        'mac' => $onuMac,
                        'pon' => $suffix !== '' ? $suffix : null,
                        'serial' => $desc,
                        'rx' => $this->parsePower($this->walkValue($rxs, $suffix, self::BDCOM_OIDS['rx'])),
                        'tx' => $this->parsePower($this->walkValue($txs, $suffix, self::BDCOM_OIDS['tx'])),
                        'status' => $this->walkValue($sts, $suffix, self::BDCOM_OIDS['status']) ?: 'online',
                    ];
                }
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $walk
     */
    private function walkValue(array $walk, string $suffix, string $baseOid): ?string
    {
        if ($suffix === '') {
            return null;
        }
        foreach ($walk as $oidKey => $value) {
            $got = SnmpClient::suffixFromOidKey((string) $oidKey, $baseOid);
            if ($got === $suffix) {
                return trim((string) $value);
            }
        }

        return null;
    }

    private function normalizeMac(string $raw): ?string
    {
        $hex = strtoupper(preg_replace('/[^0-9A-F]/', '', $raw) ?? '');
        if (strlen($hex) < 12 && $raw !== '') {
            $bytes = array_values(array_filter(explode(' ', preg_replace('/[^0-9A-Fa-f ]/', ' ', $raw) ?? '')));
            if (count($bytes) >= 6) {
                $hex = strtoupper(implode('', array_slice($bytes, 0, 6)));
            }
        }
        if (strlen($hex) < 12) {
            return null;
        }

        return implode(':', str_split(substr($hex, 0, 12), 2));
    }

    private function parsePower(?string $raw): ?float
    {
        if ($raw === null || $raw === '' || ! is_numeric($raw)) {
            return null;
        }
        $n = (float) $raw;
        if ($n > 1000) {
            $n = $n / 100;
            if ($n > 40) {
                $n = -($n - 100);
            }
        } elseif ($n > 40) {
            $n = $n / 10;
            if ($n > 0 && $n < 40) {
                $n = -$n;
            }
        }

        return round($n, 3);
    }
}
