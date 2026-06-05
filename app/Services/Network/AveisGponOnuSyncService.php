<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Olt\OltPptpTunnelService;
use App\Services\Olt\OltSnmpProbeService;
use App\Services\Optical\CustomerOnuMatcher;
use App\Services\Optical\OltOnuAutoLinkCoordinator;
use App\Services\Optical\OpticalReadingPipeline;
use App\Support\BdcomOnuDescriptionHeuristic;
use App\Support\CustomerPppLoginResolver;
use App\Support\GponSnmpProfile;
use App\Support\MacAddress;
use App\Support\SnmpClient;
use App\Support\SnmpEnvironmentalDecoder;
use Illuminate\Support\Facades\Log;

/**
 * Aveis GPON/EPON OLT (AV-OLT-XE08-L3, enterprise MIB 50224).
 *
 * Handles both Aveis GPON and Aveis EPON drivers — they share the enterprise ONU table
 * (1.3.6.1.4.1.50224.3.3.2.1.{col}). EPON ONUs are MAC-registered, so the ONU MAC column
 * is the primary identity key. SNMP table columns are auto-probed on first sync (cached on
 * the OLT) so EPON firmware with a different MIB layout works without manual .env tuning.
 * Subscriber auto-link (MAC + OLT description + FDB + PPP) runs like BDCOM when enabled.
 */
final class AveisGponOnuSyncService
{
    /** First ONU index on PON1 (0x01000101). */
    private const int INDEX_BASE = 16777472;

    public function __construct(
        private readonly OltSnmpProbeService $probe,
        private readonly OltPptpTunnelService $pptpTunnel,
        private readonly OpticalReadingPipeline $opticalPipeline,
        private readonly AveisSnmpColumnDiscoveryService $columnDiscovery,
    ) {}

    /**
     * @return array{success: bool, discovered: int, created: int, updated: int, linked: int, error: ?string}
     */
    public function syncOlt(Device $olt, bool $runSmartLink = false): array
    {
        $result = [
            'success' => false,
            'discovered' => 0,
            'created' => 0,
            'updated' => 0,
            'linked' => 0,
            'error' => null,
        ];

        if (! $this->supportsDriver($olt)) {
            $result['error'] = 'OLT driver is not Aveis GPON/EPON.';

            return $result;
        }

        try {
            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $reach = $this->pptpTunnel->ensureConnected($olt->fresh());
            if (! $reach['success']) {
                throw new \RuntimeException($reach['message'] ?? 'OLT unreachable');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);

            try {
                $this->probe->fetchSysDescr($olt);
            } catch (\Throwable $e) {
                throw new \RuntimeException(
                    $e->getMessage()."\n\n".$this->probe->networkReachabilityHint($olt),
                );
            }

            $profileOids = GponSnmpProfile::forOlt($olt);
            $timeoutUs = (int) config('gpon.aveis_gpon_walk_timeout_us', 10000000);
            $retries = max(1, (int) config('snmp.retries', 1));

            // Fast path: profile columns first; full column probe only if still 0 ONUs.
            $oids = $this->columnDiscovery->resolveColumns($olt->fresh(), $profileOids, $peer, $community, $timeoutUs, $retries, false);

            $discovered = $this->inventoryFromOids($olt, $peer, $community, $oids, $timeoutUs, $retries);
            $result['sync_mode'] = 'snmp_walk';

            if (count($discovered) === 0) {
                $oids = $this->columnDiscovery->resolveColumns($olt->fresh(), $profileOids, $peer, $community, $timeoutUs, $retries, true);
                $discovered = $this->inventoryFromOids($olt, $peer, $community, $oids, $timeoutUs, $retries);
                $result['sync_mode'] = 'snmp_probe_columns';
            }

            if (count($discovered) === 0 && config('gpon.aveis_index_scan_enabled', true)) {
                $discovered = $this->inventoryViaIndexScan($olt, $peer, $community, $oids);
                $result['sync_mode'] = 'snmp_index_scan';
            }

            $result['discovered'] = count($discovered);
            $result['columns_auto'] = is_array($olt->fresh()->meta['aveis_snmp_column_map'] ?? null);

            foreach ($discovered as $row) {
                $this->upsertOnu($olt, $row, $result);
            }

            if ($this->shouldRunAutoLink($runSmartLink)) {
                $linkOut = app(OltOnuAutoLinkCoordinator::class)->runAfterOltInventory($olt->fresh());
                $result['linked'] = (int) ($linkOut['auto_link']['linked'] ?? $result['linked']);
                $result['fdb_macs_stored'] = (int) ($linkOut['fdb']['macs_stored'] ?? 0);
                $result['auto_link_detail'] = $linkOut['auto_link'];
            }

            if ($result['discovered'] === 0) {
                $result['success'] = false;
                $result['error'] = $this->zeroOnuMessage($olt, $peer);
            } else {
                $result['success'] = true;
                $olt->forceFill([
                    'status' => 'active',
                    'last_snmp_poll_at' => now(),
                    'last_polled_at' => now(),
                ])->saveQuietly();
            }
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('aveis_gpon_sync.failed', ['olt_id' => $olt->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    public function supportsDriver(Device $olt): bool
    {
        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $profile = strtolower((string) ($olt->gpon_profile ?? ''));
        $vendor = strtolower((string) ($olt->vendor ?? ''));

        return in_array($driver, ['aveis_gpon', 'aveis_epon', 'aveis_xpon'], true)
            || in_array($profile, ['aveis_gpon', 'aveis_epon'], true)
            || $vendor === 'aveis';
    }

    private function shouldRunAutoLink(bool $runSmartLink): bool
    {
        if ($runSmartLink) {
            return true;
        }

        return (bool) config('optical.auto_link_on_olt_sync', config('optical.auto_link_on_bdcom_sync', true));
    }

    /**
     * @param  array<string, mixed>  $oids
     * @return list<array<string, mixed>>
     */
    private function inventoryFromOids(Device $olt, string $peer, string $community, array $oids, int $timeoutUs, int $retries): array
    {
        $table = (string) ($oids['aveis_onu_table'] ?? '1.3.6.1.4.1.50224.3.3.2.1');

        $labelCol = max(1, (int) ($oids['aveis_onu_label_column'] ?? 2));
        $statusCol = max(1, (int) ($oids['aveis_onu_status_column'] ?? 3));
        $macCol = max(1, (int) ($oids['aveis_onu_mac_column'] ?? 7));
        $nameCol = max(1, (int) ($oids['aveis_onu_name_column'] ?? 12));

        $labels = $this->walkColumn($peer, $community, $table.'.'.$labelCol, $timeoutUs, $retries);
        $statuses = $this->walkColumn($peer, $community, $table.'.'.$statusCol, $timeoutUs, $retries);
        $macs = $this->walkColumn($peer, $community, $table.'.'.$macCol, $timeoutUs, $retries);
        $names = $this->walkColumn($peer, $community, $table.'.'.$nameCol, $timeoutUs, $retries);

        $rxColumn = max(1, (int) ($oids['aveis_onu_rx_column'] ?? config('gpon.aveis_onu_rx_column', 15)));
        $rxRaw = $this->walkColumn($peer, $community, $table.'.'.$rxColumn, $timeoutUs, $retries);

        $txColumn = (int) ($oids['aveis_onu_tx_column'] ?? 0);
        $tempColumn = (int) ($oids['aveis_onu_temp_column'] ?? 0);
        $voltColumn = (int) ($oids['aveis_onu_voltage_column'] ?? 0);
        $distColumn = (int) ($oids['aveis_onu_distance_column'] ?? 0);

        $txRaw = $txColumn > 0 ? $this->walkColumn($peer, $community, $table.'.'.$txColumn, $timeoutUs, $retries) : [];
        $tempRaw = $tempColumn > 0 ? $this->walkColumn($peer, $community, $table.'.'.$tempColumn, $timeoutUs, $retries) : [];
        $voltRaw = $voltColumn > 0 ? $this->walkColumn($peer, $community, $table.'.'.$voltColumn, $timeoutUs, $retries) : [];
        $distRaw = $distColumn > 0 ? $this->walkColumn($peer, $community, $table.'.'.$distColumn, $timeoutUs, $retries) : [];

        $indices = array_unique(array_merge(
            array_keys($labels),
            array_keys($statuses),
            array_keys($macs),
        ));

        $vendorProfile = GponSnmpProfile::profileKeyForOlt($olt);
        $discovered = [];

        foreach ($indices as $idx) {
            $parsed = self::parseAveisIndex((int) $idx);
            if ($parsed === null) {
                continue;
            }

            $label = trim((string) ($labels[$idx] ?? ''));
            if ($label === '') {
                $label = sprintf('PON%d:%d', $parsed['pon_no'], $parsed['onu_index']);
            }

            $mac = $this->parseMacHex($macs[$idx] ?? null)
                ?? $this->parseMacHex($names[$idx] ?? null)
                ?? $this->parseMacHex($labels[$idx] ?? null);

            $equipmentId = trim((string) ($names[$idx] ?? ''));
            $description = $this->resolveSubscriberDescription((int) $olt->tenant_id, $equipmentId, $label);

            $serial = 'AV-'.$idx;
            if ($mac !== null) {
                $serial = 'AV-'.str_replace(':', '', strtoupper($mac));
            }

            $distance = isset($distRaw[$idx]) ? $this->parseNumber($distRaw[$idx]) : null;
            $rx = self::decodeAveisRx($this->parseNumber($rxRaw[$idx] ?? null));
            $txRawVal = $this->parseNumber($txRaw[$idx] ?? null);
            $tx = $txRawVal !== null ? self::decodeAveisRx($txRawVal) : null;

            $discovered[] = [
                'index' => (string) $idx,
                'serial' => $serial,
                'equipment_id' => $equipmentId,
                'description' => $description,
                'card_no' => $parsed['card_no'],
                'pon_no' => $parsed['pon_no'],
                'onu_index' => $parsed['onu_index'],
                'label' => $label,
                'mac' => $mac,
                'oper_status' => $this->mapStatus($this->parseNumber($statuses[$idx] ?? null)),
                'distance_m' => $distance,
                'rx_dbm' => $rx,
                'tx_dbm' => $tx,
                'temperature_c' => isset($tempRaw[$idx])
                    ? SnmpEnvironmentalDecoder::temperatureC($this->parseNumber($tempRaw[$idx]), $vendorProfile)
                    : null,
                'voltage_v' => isset($voltRaw[$idx])
                    ? SnmpEnvironmentalDecoder::voltageV($this->parseNumber($voltRaw[$idx]), $vendorProfile)
                    : null,
            ];
        }

        return $discovered;
    }

    /**
     * Best OLT-side hint for PPP login / customer code (BDCOM description equivalent).
     */
    private function resolveSubscriberDescription(int $tenantId, string $equipmentId, string $label): string
    {
        foreach ([$equipmentId, $label] as $hint) {
            $hint = trim($hint);
            if ($hint === '') {
                continue;
            }
            if (BdcomOnuDescriptionHeuristic::shouldSkipDescriptionForLinking($tenantId, $hint)) {
                continue;
            }

            return $hint;
        }

        if (! BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel($equipmentId)) {
            return trim($equipmentId);
        }

        return trim($label);
    }

    public static function buildAveisIndex(int $ponNo, int $onuIndex): int
    {
        return self::INDEX_BASE + max(0, $ponNo - 1) * 256 + $onuIndex;
    }

    /**
     * @param  array<string, mixed>  $oids
     * @return list<array<string, mixed>>
     */
    private function inventoryViaIndexScan(Device $olt, string $peer, string $community, array $oids): array
    {
        $table = (string) ($oids['aveis_onu_table'] ?? '1.3.6.1.4.1.50224.3.3.2.1');
        $macCol = max(1, (int) ($oids['aveis_onu_mac_column'] ?? 7));
        $statusCol = max(1, (int) ($oids['aveis_onu_status_column'] ?? 3));
        $labelCol = max(1, (int) ($oids['aveis_onu_label_column'] ?? 2));
        $nameCol = max(1, (int) ($oids['aveis_onu_name_column'] ?? 12));
        $rxCol = max(1, (int) ($oids['aveis_onu_rx_column'] ?? config('gpon.aveis_onu_rx_column', 15)));

        $getTimeout = (int) config('gpon.aveis_index_get_timeout_us', 600000);
        $maxPon = max(1, (int) config('gpon.aveis_index_scan_max_pon', 8));
        $maxOnu = max(1, (int) config('gpon.aveis_index_scan_max_onu', 64));
        $vendorProfile = GponSnmpProfile::profileKeyForOlt($olt);

        $discovered = [];

        for ($ponNo = 1; $ponNo <= $maxPon; $ponNo++) {
            $emptyStreak = 0;
            for ($onuIndex = 1; $onuIndex <= $maxOnu; $onuIndex++) {
                $idx = (string) self::buildAveisIndex($ponNo, $onuIndex);
                $macRaw = SnmpClient::get($peer, $community, $table.'.'.$macCol.'.'.$idx, $getTimeout, 1);
                $statusRaw = SnmpClient::get($peer, $community, $table.'.'.$statusCol.'.'.$idx, $getTimeout, 1);

                if ($macRaw === null && $statusRaw === null) {
                    $emptyStreak++;
                    if ($emptyStreak >= 12) {
                        break;
                    }

                    continue;
                }

                $emptyStreak = 0;
                $label = trim((string) (SnmpClient::get($peer, $community, $table.'.'.$labelCol.'.'.$idx, $getTimeout, 1) ?? ''));
                if ($label === '') {
                    $label = sprintf('PON%d:%d', $ponNo, $onuIndex);
                }

                $nameRaw = SnmpClient::get($peer, $community, $table.'.'.$nameCol.'.'.$idx, $getTimeout, 1);
                $mac = $this->parseMacHex($macRaw) ?? $this->parseMacHex($nameRaw) ?? $this->parseMacHex($label);
                $equipmentId = trim((string) ($nameRaw ?? ''));
                $description = $this->resolveSubscriberDescription((int) $olt->tenant_id, $equipmentId, $label);

                $serial = $mac !== null
                    ? 'AV-'.str_replace(':', '', strtoupper($mac))
                    : 'AV-'.$idx;

                $rx = self::decodeAveisRx($this->parseNumber(
                    SnmpClient::get($peer, $community, $table.'.'.$rxCol.'.'.$idx, $getTimeout, 1),
                ));

                $discovered[] = [
                    'index' => $idx,
                    'serial' => $serial,
                    'equipment_id' => $equipmentId,
                    'description' => $description,
                    'card_no' => 1,
                    'pon_no' => $ponNo,
                    'onu_index' => $onuIndex,
                    'label' => $label,
                    'mac' => $mac,
                    'oper_status' => $this->mapStatus($this->parseNumber($statusRaw)),
                    'distance_m' => null,
                    'rx_dbm' => $rx,
                    'tx_dbm' => null,
                    'temperature_c' => null,
                    'voltage_v' => null,
                ];
            }
        }

        return $discovered;
    }

    private function zeroOnuMessage(Device $olt, string $peer): string
    {
        $diag = app(\App\Services\Olt\AveisOltDiagnosticsService::class)->diagnose($olt);

        return implode("\n", array_filter([
            'SNMP reachable but 0 ONUs imported from '.$peer.'.',
            $diag['summary'],
            ...$diag['hints'],
            'অন্য প্যানেলে চললে সেটা লোকাল নেটওয়ার্কে থাকতে পারে — বিল সার্ভার IP OLT SNMP ACL-এ allow করুন।',
        ]));
    }

    /**
     * @return array{card_no: int, pon_no: int, onu_index: int}|null
     */
    public static function parseAveisIndex(int $idx): ?array
    {
        if ($idx <= self::INDEX_BASE) {
            return null;
        }

        $offset = $idx - self::INDEX_BASE;
        $ponNo = intdiv($offset, 256) + 1;
        $onuIndex = $offset % 256;

        if ($onuIndex < 1) {
            return null;
        }

        return [
            'card_no' => 1,
            'pon_no' => $ponNo,
            'onu_index' => $onuIndex,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function walkColumn(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $out = [];
        $walker = config('gpon.aveis_snmp_use_unchecked_walk', true)
            ? SnmpClient::realWalkUnchecked($peer, $community, $oid, $timeoutUs, $retries)
            : SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries);

        foreach ($walker as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null || ! is_numeric($suffix)) {
                continue;
            }
            $out[$suffix] = trim((string) $value);
        }

        return $out;
    }

    private function parseMacHex(?string $raw): ?string
    {
        return MacAddress::fromSnmpValue($raw);
    }

    private function parseNumber(?string $raw): ?int
    {
        if ($raw === null || $raw === '') {
            return null;
        }

        $clean = trim(preg_replace('/^[A-Za-z-]+:\s*/', '', $raw) ?? '');
        $clean = trim($clean, "\" \t");

        return is_numeric($clean) ? (int) $clean : null;
    }

    /**
     * Decode Aveis SNMP receive-power integer (OLT UI “Receive Power” column).
     */
    public static function decodeAveisRx(?int $raw): ?float
    {
        if ($raw === null || $raw === 0) {
            return null;
        }

        $rawMin = (int) config('gpon.aveis_rx_raw_min', 400);
        if ($rawMin > 0 && $raw < $rawMin) {
            return null;
        }

        $rawMax = (int) config('gpon.aveis_rx_raw_max', 2000);
        if ($rawMax > 0 && $raw > $rawMax) {
            return null;
        }

        $mode = (string) config('gpon.aveis_rx_mode', 'col15_divisor');

        $dbm = match ($mode) {
            'col15_divisor', 'divisor' => round(-$raw / (float) config('gpon.aveis_rx_divisor', 57.3), 2),
            'negative_tenth' => ($raw > 0 && $raw < 150) ? round(-$raw / 10, 2) : null,
            'tenth_dbm' => round($raw / 10, 2),
            'skip' => null,
            default => null,
        };

        if ($dbm === null) {
            return null;
        }

        $floor = (float) config('gpon.aveis_rx_dbm_floor', -35);
        if ($dbm < $floor) {
            return null;
        }

        return $dbm;
    }

    private function mapStatus(?int $code): string
    {
        return match ($code) {
            1 => 'online',
            2 => 'offline',
            3 => 'los',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $stats
     */
    private function upsertOnu(Device $olt, array $row, array &$stats): void
    {
        $serial = (string) $row['serial'];
        $mac = filled($row['mac'] ?? null) ? (string) $row['mac'] : null;

        $onu = Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->where(function ($q) use ($serial, $row, $mac): void {
                $q->where('onu_external_id', (string) $row['index'])
                    ->orWhere('serial_number', $serial);

                // EPON ONUs are MAC-registered: match an existing record by MAC so a re-ranged
                // ONU (new SNMP index) updates in place instead of duplicating.
                if ($mac !== null) {
                    $q->orWhere(function ($macQuery) use ($mac): void {
                        MacAddress::applyOnuMacMatch($macQuery, $mac);
                    });
                }
            })
            ->first();

        $isNew = $onu === null;
        if ($isNew) {
            $onu = new Device([
                'tenant_id' => $olt->tenant_id,
                'type' => 'onu',
                'olt_id' => $olt->id,
                'serial_number' => $serial,
                'status' => 'assigned',
            ]);
            $stats['created']++;
        } else {
            $stats['updated']++;
        }

        $tenantId = (int) $olt->tenant_id;
        $macCompact = $mac !== null ? strtoupper(str_replace(':', '', $mac)) : '';
        $customer = null;
        if ($mac !== null && $macCompact !== '') {
            $customer = CustomerOnuMatcher::matchCustomerByOnuMac($tenantId, $mac, $macCompact);
        }

        $description = trim((string) ($row['description'] ?? ''));
        if ($customer === null && $description !== ''
            && ! BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel($description)) {
            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('customer_code', $description)
                ->first();

            if ($customer === null) {
                $resolved = CustomerPppLoginResolver::resolve($tenantId, $description);
                if ($resolved !== null
                    && CustomerPppLoginResolver::normalize($description) === CustomerPppLoginResolver::normalize($resolved->pppLoginName())) {
                    $customer = $resolved;
                }
            }
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $meta['aveis_snmp_index'] = $row['index'];
        $meta['aveis_label'] = $row['label'];
        $meta['last_aveis_sync'] = now()->toIso8601String();
        if ($row['distance_m'] !== null) {
            $meta['distance_m'] = $row['distance_m'];
            $meta['aveis_distance_m'] = $row['distance_m'];
        }

        if ($description !== '') {
            $meta['aveis_description'] = $description;
        }
        if (filled($row['equipment_id'] ?? null)) {
            $meta['aveis_equipment_id'] = $row['equipment_id'];
        }

        $login = $customer?->pppLoginName() ?? '';
        if ($login === '') {
            $login = BdcomOnuDescriptionHeuristic::sanitizePppLoginHint($description, $tenantId) ?? '';
        }
        if ($login !== '') {
            $meta['ppp_login'] = $login;
        } elseif (isset($meta['ppp_login']) && BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel((string) $meta['ppp_login'])) {
            unset($meta['ppp_login']);
        }

        $externalId = $onu->onu_external_id;
        if (blank($externalId) || BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel((string) $externalId)) {
            $externalId = $login !== '' ? $login : (string) $row['index'];
        }

        $onu->forceFill([
            'display_name' => $row['label'],
            'mac_address' => $row['mac'],
            'card_no' => $row['card_no'],
            'pon_no' => $row['pon_no'],
            'onu_index' => $row['onu_index'],
            'onu_external_id' => $externalId,
            'onu_oper_status' => $row['oper_status'],
            'customer_id' => $onu->customer_id ?? $customer?->id,
            'gpon_profile' => GponSnmpProfile::profileKeyForOlt($olt),
            'meta' => $meta,
            'last_polled_at' => now(),
        ])->save();

        if ($customer !== null && $onu->customer_id === $customer->id) {
            $stats['linked']++;
        }

        if ($row['rx_dbm'] !== null || $row['tx_dbm'] !== null || ($row['temperature_c'] ?? null) !== null || ($row['voltage_v'] ?? null) !== null) {
            $this->opticalPipeline->ingest($onu->fresh(), [
                'rx_raw' => $row['rx_dbm'],
                'tx_raw' => $row['tx_dbm'],
                'temperature' => $row['temperature_c'] ?? null,
                'voltage' => $row['voltage_v'] ?? null,
                'already_dbm' => true,
                'oper_status' => $row['oper_status'],
                'vendor_profile' => GponSnmpProfile::profileKeyForOlt($olt),
                'source' => 'aveis_snmp',
                'bypass_smoothing' => true,
            ]);
        } elseif ($onu->rx_power_dbm !== null || $onu->tx_power_dbm !== null || isset($meta['optical'])) {
            unset($meta['optical']);
            $onu->forceFill([
                'rx_power_dbm' => null,
                'tx_power_dbm' => null,
                'meta' => $meta,
            ])->save();
        }
    }
}
