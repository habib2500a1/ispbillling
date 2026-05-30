<?php

namespace App\Services\Network;

use App\Models\Device;
use App\Services\Olt\OltSnmpProbeService;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Log;

/**
 * OLT forwarding-database (FDB) → ONU MAC bridge — vendor agnostic.
 *
 * An EPON/GPON OLT only knows the ONU's own hardware MAC, which never matches the MikroTik PPPoE
 * caller_id (that is the CUSTOMER router MAC behind the ONU). The bridge between them is the OLT's
 * own forwarding table: it learns every customer MAC that passes through each ONU's bridge port.
 *
 * This walks the standard BRIDGE-MIB (dot1qTpFdbPort → bridge port; dot1dBasePortIfIndex →
 * ifIndex), resolves ifIndex → ONU device, and persists the learned customer MACs on the ONU as
 * meta['fdb_macs']. IspDigitalOnuAutoLinkService then matches those MACs to ppp_session_logs.caller_id.
 *
 * The ifIndex → ONU resolution is multi-source so it works across vendors:
 *   1. a stored ifIndex (meta['snmp_if_index'] or meta['bdcom_if_index']);
 *   2. a label fallback — walk IF-MIB ifDescr/ifName, map ifIndex → label, and match the ONU's
 *      display_name / onu_external_id / bdcom_label.
 *
 * Hardware-validated on BDCOM EPON. Other vendors are attempted best-effort: an OLT that exposes no
 * usable per-ONU FDB simply stores 0 MACs (safe no-op) and the description/login matchers take over.
 */
final class OltFdbMacBridgeService
{
    public function __construct(
        private readonly OltSnmpProbeService $probe,
    ) {}

    /**
     * Walk the OLT FDB and store learned customer MACs on each ONU.
     *
     * @return array{success: bool, fdb_entries: int, onus_with_macs: int, macs_stored: int, error: ?string}
     */
    public function collectForOlt(Device $olt): array
    {
        $result = [
            'success' => false,
            'fdb_entries' => 0,
            'onus_with_macs' => 0,
            'macs_stored' => 0,
            'error' => null,
        ];

        if (! $this->fdbEnabledFor($olt)) {
            $result['error'] = 'FDB MAC bridge disabled for driver «'.($olt->olt_driver ?? 'unset').'».';

            return $result;
        }

        try {
            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);
            $oids = $this->fdbOids();
            $timeoutUs = (int) config('gpon.bdcom_epon_walk_timeout_us', 8000000);
            $retries = (int) config('snmp.retries', 1);

            // Fast reachability gate: an offline/unreachable OLT must fail in ~2.5s, not stall the
            // 3-minute pipeline through five full-timeout walks.
            if (SnmpClient::get($peer, $community, '1.3.6.1.2.1.1.1.0', 2500000, 0) === null) {
                $result['error'] = 'OLT did not respond to SNMP (sysDescr) — offline or wrong community.';

                return $result;
            }

            // bridgePort → ifIndex
            $portToIf = $this->walkBasePortIfIndex($peer, $community, $oids['baseport'], $timeoutUs, $retries);

            // ifIndex → ONU device id (stored ifIndex + IF-MIB label fallback)
            $ifToOnuId = $this->resolveIfIndexToOnu($olt, $peer, $community, $oids, $timeoutUs, $retries);

            // MAC → bridgePort (FDB). Q-BRIDGE first, fall back to classic dot1d.
            $fdb = $this->walkFdb($peer, $community, $oids['qbridge'], true, $timeoutUs, $retries);
            if ($fdb === []) {
                $fdb = $this->walkFdb($peer, $community, $oids['dot1d'], false, $timeoutUs, $retries);
            }
            $result['fdb_entries'] = count($fdb);

            // Group learned MACs per ONU device.
            $macsByOnu = [];
            foreach ($fdb as $entry) {
                $ifIndex = $portToIf[$entry['port']] ?? $entry['port'];
                $onuId = $ifToOnuId[$ifIndex] ?? null;
                if ($onuId === null) {
                    continue;
                }
                $macsByOnu[$onuId][$entry['mac']] = true;
            }

            $stored = 0;
            foreach ($macsByOnu as $onuId => $macSet) {
                $macs = array_keys($macSet);
                $onu = Device::query()->withoutGlobalScopes()->find($onuId);
                if ($onu === null) {
                    continue;
                }
                $meta = is_array($onu->meta) ? $onu->meta : [];
                $meta['fdb_macs'] = $macs;
                $meta['fdb_synced_at'] = now()->toIso8601String();
                $onu->forceFill(['meta' => $meta])->saveQuietly();
                $stored += count($macs);
            }

            $result['onus_with_macs'] = count($macsByOnu);
            $result['macs_stored'] = $stored;
            $result['success'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('olt_fdb_bridge.failed', ['olt_id' => $olt->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    /**
     * Whether to attempt the FDB bridge on this OLT (config-gated by driver; empty list = all).
     */
    public function fdbEnabledFor(Device $olt): bool
    {
        $drivers = (array) config('optical.fdb_bridge_drivers', []);
        if ($drivers === []) {
            return true;
        }

        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $profile = strtolower((string) ($olt->gpon_profile ?? ''));

        return in_array($driver, $drivers, true) || in_array($profile, $drivers, true);
    }

    /** Back-compat: BDCOM EPON specifically supports the standard FDB. */
    public function supportsDriver(Device $olt): bool
    {
        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $profile = strtolower((string) ($olt->gpon_profile ?? ''));

        return $driver === 'bdcom_epon' || $profile === 'bdcom_epon';
    }

    /**
     * Standard BRIDGE-MIB + IF-MIB OIDs (vendor-neutral, from the generic profile with defaults).
     *
     * @return array{qbridge: string, dot1d: string, baseport: string, if_descr: string, if_name: string}
     */
    private function fdbOids(): array
    {
        $g = (array) config('gpon.profiles.generic_gpon', []);

        return [
            'qbridge' => (string) ($g['fdb_qbridge'] ?? '1.3.6.1.2.1.17.7.1.2.2.1.2'),
            'dot1d' => (string) ($g['fdb_dot1d'] ?? '1.3.6.1.2.1.17.4.3.1.2'),
            'baseport' => (string) ($g['fdb_baseport_ifindex'] ?? '1.3.6.1.2.1.17.1.4.1.2'),
            'if_descr' => (string) ($g['if_descr'] ?? '1.3.6.1.2.1.2.2.1.2'),
            'if_name' => (string) ($g['if_name'] ?? '1.3.6.1.2.1.31.1.1.1.1'),
        ];
    }

    /**
     * dot1dBasePortIfIndex: bridgePort → ifIndex.
     *
     * @return array<int, int>
     */
    private function walkBasePortIfIndex(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $map = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null || ! ctype_digit($suffix)) {
                continue;
            }
            $ifIndex = (int) preg_replace('/\D/', '', (string) $value);
            if ($ifIndex > 0) {
                $map[(int) $suffix] = $ifIndex;
            }
        }

        return $map;
    }

    /**
     * Build ifIndex → ONU device id for this OLT using (1) a stored ifIndex and (2) an IF-MIB label
     * fallback that matches ONU labels to ifDescr/ifName entries.
     *
     * @param  array{qbridge: string, dot1d: string, baseport: string, if_descr: string, if_name: string}  $oids
     * @return array<int, int>
     */
    private function resolveIfIndexToOnu(Device $olt, string $peer, string $community, array $oids, int $timeoutUs, int $retries): array
    {
        $map = [];
        $needLabelFallback = [];

        Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->select(['id', 'display_name', 'onu_external_id', 'meta'])
            ->chunkById(200, function ($onus) use (&$map, &$needLabelFallback): void {
                foreach ($onus as $onu) {
                    $meta = is_array($onu->meta) ? $onu->meta : [];
                    $ifIndex = (int) ($meta['snmp_if_index'] ?? $meta['bdcom_if_index'] ?? 0);
                    if ($ifIndex > 0) {
                        $map[$ifIndex] = (int) $onu->id;

                        continue;
                    }
                    foreach ($this->onuLabels($onu, $meta) as $label) {
                        $needLabelFallback[$label] = (int) $onu->id;
                    }
                }
            });

        // Label fallback: only walk IF-MIB if some ONUs had no stored ifIndex.
        if ($needLabelFallback !== []) {
            $ifLabels = $this->walkIfLabels($peer, $community, $oids['if_descr'], $oids['if_name'], $timeoutUs, $retries);
            foreach ($ifLabels as $ifIndex => $label) {
                $onuId = $needLabelFallback[$label] ?? null;
                if ($onuId !== null && ! isset($map[$ifIndex])) {
                    $map[$ifIndex] = $onuId;
                }
            }
        }

        return $map;
    }

    /**
     * Normalised labels an ONU may be known by in the IF-MIB.
     *
     * @param  array<string, mixed>  $meta
     * @return list<string>
     */
    private function onuLabels(Device $onu, array $meta): array
    {
        $labels = [
            $onu->display_name,
            $onu->onu_external_id,
            $meta['bdcom_label'] ?? null,
            $meta['aveis_label'] ?? null,
            $meta['vsol_description'] ?? null,
        ];

        $out = [];
        foreach ($labels as $label) {
            $norm = $this->normalizeLabel((string) $label);
            if ($norm !== '') {
                $out[$norm] = true;
            }
        }

        return array_keys($out);
    }

    /**
     * Walk ifDescr + ifName → ifIndex → normalised label.
     *
     * @return array<int, string>
     */
    private function walkIfLabels(string $peer, string $community, string $ifDescr, string $ifName, int $timeoutUs, int $retries): array
    {
        $out = [];
        foreach ([$ifDescr, $ifName] as $oid) {
            foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
                $suffix = SnmpClient::suffixFromOidKey($key, $oid);
                if ($suffix === null || ! ctype_digit($suffix)) {
                    continue;
                }
                $norm = $this->normalizeLabel((string) $value);
                if ($norm !== '') {
                    // ifDescr is walked first; don't let a blank ifName override a good ifDescr label.
                    $out[(int) $suffix] = $out[(int) $suffix] ?? $norm;
                }
            }
        }

        return $out;
    }

    private function normalizeLabel(string $label): string
    {
        $label = strtoupper(trim($label));
        $label = preg_replace('/^[A-Z-]+:\s*/', '', $label) ?? $label; // strip SNMP "STRING: " prefix
        $label = preg_replace('/\s+/', '', $label) ?? $label;

        return trim($label, "\"");
    }

    /**
     * Walk an FDB table. Q-BRIDGE suffix = <vlan>.<6 mac octets>; classic dot1d suffix = <6 mac octets>.
     * Value is the bridge port. BDCOM returns the Q-BRIDGE table out of order, so the increasing
     * check must be disabled or the walk truncates after ~20 rows.
     *
     * @return list<array{mac: string, port: int}>
     */
    private function walkFdb(string $peer, string $community, string $oid, bool $perVlan, int $timeoutUs, int $retries): array
    {
        $rows = [];
        $walk = $perVlan
            ? SnmpClient::realWalkUnchecked($peer, $community, $oid, $timeoutUs, $retries)
            : SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries);

        foreach ($walk as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null) {
                continue;
            }
            $octets = explode('.', $suffix);
            if (count($octets) < 6) {
                continue;
            }
            // Last 6 octets are the MAC (the optional leading VLAN id is ignored).
            $macOctets = array_slice($octets, -6);
            $mac = '';
            foreach ($macOctets as $octet) {
                if (! ctype_digit($octet) || (int) $octet > 255) {
                    $mac = '';
                    break;
                }
                $mac .= sprintf('%02X', (int) $octet);
            }
            if (strlen($mac) !== 12) {
                continue;
            }
            $port = (int) preg_replace('/\D/', '', (string) $value);
            if ($port <= 0) {
                continue;
            }
            $rows[] = ['mac' => $mac, 'port' => $port];
        }

        return $rows;
    }
}
