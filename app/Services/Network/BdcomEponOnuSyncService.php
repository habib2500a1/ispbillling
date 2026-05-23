<?php

namespace App\Services\Network;

use App\Models\Customer;
use App\Models\Device;
use App\Services\Olt\OltSnmpProbeService;
use App\Services\Optical\CustomerOnuSmartLinkService;
use App\Services\Optical\OpticalReadingPipeline;
use App\Support\CustomerPppLoginResolver;
use App\Support\BdcomOnuDescriptionHeuristic;
use App\Support\MacAddress;
use App\Support\SnmpClient;
use Illuminate\Support\Facades\Log;

final class BdcomEponOnuSyncService
{
    public function __construct(
        private readonly OltSnmpProbeService $probe,
        private readonly OpticalReadingPipeline $opticalPipeline,
    ) {}

    /**
     * @return array{
     *   success: bool,
     *   discovered: int,
     *   created: int,
     *   updated: int,
     *   linked: int,
     *   deleted_offline: int,
     *   error: ?string
     * }
     */
    public function syncOlt(Device $olt, bool $deleteOfflineFromInventory = false): array
    {
        $result = [
            'success' => false,
            'discovered' => 0,
            'created' => 0,
            'updated' => 0,
            'linked' => 0,
            'deleted_offline' => 0,
            'error' => null,
        ];

        if (! $this->supportsDriver($olt)) {
            $result['error'] = 'OLT driver is not BDCOM EPON.';

            return $result;
        }

        try {
            if (! SnmpClient::available()) {
                throw new \RuntimeException('PHP ext-snmp is not loaded.');
            }

            $peer = $this->probe->snmpPeer($olt);
            $community = $this->probe->effectiveCommunity($olt);
            $oids = config('gpon.profiles.bdcom_epon', []);

            $timeoutUs = (int) config('gpon.bdcom_epon_walk_timeout_us', 8000000);
            $retries = (int) config('snmp.retries', 1);

            $ifMap = $this->walkEponInterfaces($peer, $community, (string) ($oids['if_descr'] ?? '1.3.6.1.2.1.2.2.1.2'), $timeoutUs, $retries);
            $macByIf = $this->walkMacByIfIndex($peer, $community, (string) ($oids['bdcom_epon_onu_mac'] ?? '1.3.6.1.4.1.3320.101.10.1.1.3'), $timeoutUs, $retries);
            // Raw SNMP integers (0.1 dBm) — normalized once in OpticalReadingPipeline (bdcom_epon).
            $rxByIf = $this->walkRawSnmpDbm($peer, $community, (string) ($oids['bdcom_epon_onu_rx'] ?? '1.3.6.1.4.1.3320.101.10.5.1.5'), $timeoutUs, $retries);
            $txByIf = $this->walkRawSnmpDbm($peer, $community, (string) ($oids['bdcom_epon_onu_tx'] ?? '1.3.6.1.4.1.3320.101.10.5.1.6'), $timeoutUs, $retries);
            $statusByPonOnu = $this->walkOnuStatus($peer, $community, (string) ($oids['bdcom_epon_onu_status'] ?? '1.3.6.1.4.1.3320.101.11.4.1.5'), $timeoutUs, $retries);
            $descByIf = $this->walkStringsByIfIndex(
                $peer,
                $community,
                (string) ($oids['bdcom_epon_onu_desc'] ?? '1.3.6.1.4.1.3320.101.10.1.1.2'),
                $timeoutUs,
                $retries,
            );

            $discovered = [];
            foreach ($ifMap as $ifIndex => $info) {
                if ($info['kind'] !== 'onu') {
                    continue;
                }

                $mac = $macByIf[$ifIndex] ?? null;
                if ($mac === null) {
                    continue;
                }

                $statusKey = $info['pon_ifindex'].'.'.$info['onu_index'];
                $statusCode = $statusByPonOnu[$statusKey] ?? null;

                $discovered[] = [
                    'if_index' => $ifIndex,
                    'mac' => $mac,
                    'card_no' => $info['card_no'],
                    'pon_no' => $info['pon_no'],
                    'onu_index' => $info['onu_index'],
                    'label' => $info['label'],
                    'description' => $descByIf[$ifIndex] ?? null,
                    'rx_dbm' => $rxByIf[$ifIndex] ?? null,
                    'tx_dbm' => $txByIf[$ifIndex] ?? null,
                    'oper_status' => $this->mapStatusCode($statusCode),
                    'bdcom_status' => $statusCode,
                ];
            }

            $result['discovered'] = count($discovered);
            $seenIds = [];

            foreach ($discovered as $row) {
                $onu = $this->upsertOnu($olt, $row, $result);
                if ($onu !== null) {
                    $seenIds[] = $onu->id;
                }
            }

            if ($deleteOfflineFromInventory) {
                $result['deleted_offline'] = $this->deleteOfflineOnus($olt, $discovered);
            }

            $result['purged_placeholders'] = $this->purgeAutoProvisionedPlaceholders($olt);

            // Full tenant auto-link runs once in IspDigitalOnuPipelineService (MikroTik + PPP + smart link).
            if (config('optical.auto_link_on_bdcom_sync', true)
                && ! config('optical.isp_digital_auto_sync', true)) {
                $linkStats = app(CustomerOnuSmartLinkService::class)
                    ->smartRelinkTenant((int) $olt->tenant_id, true);
                $result['linked'] = $linkStats['linked'];
                $result['pruned'] = $linkStats['pruned'];
                $result['link_conflicts'] = $linkStats['conflicts'];
            }

            $result['success'] = true;
        } catch (\Throwable $e) {
            $result['error'] = $e->getMessage();
            Log::warning('bdcom_epon_sync.failed', ['olt_id' => $olt->id, 'error' => $e->getMessage()]);
        }

        return $result;
    }

    public function supportsDriver(Device $olt): bool
    {
        $driver = strtolower((string) ($olt->olt_driver ?? ''));
        $profile = strtolower((string) ($olt->gpon_profile ?? ''));

        return $driver === 'bdcom_epon' || $profile === 'bdcom_epon';
    }

    /**
     * @return array<int, array{kind: string, label: string, card_no: ?int, pon_no: ?int, onu_index: ?int, pon_ifindex: ?int}>
     */
    private function walkEponInterfaces(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $map = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null || ! ctype_digit($suffix)) {
                continue;
            }
            $ifIndex = (int) $suffix;
            $label = $this->cleanSnmpValue((string) $value);
            if (! preg_match('/^EPON(\d+)\/(\d+)(?::(\d+))?$/i', $label, $m)) {
                continue;
            }

            $card = (int) $m[1];
            $pon = (int) $m[2];
            $onuIdx = isset($m[3]) ? (int) $m[3] : null;

            if ($onuIdx === null) {
                $map[$ifIndex] = [
                    'kind' => 'pon',
                    'label' => $label,
                    'card_no' => $card,
                    'pon_no' => $pon,
                    'onu_index' => null,
                    'pon_ifindex' => $ifIndex,
                ];
            } else {
                $ponKey = "{$card}/{$pon}";
                $ponIf = null;
                foreach ($map as $idx => $row) {
                    if ($row['kind'] === 'pon' && $row['card_no'] === $card && $row['pon_no'] === $pon) {
                        $ponIf = $idx;
                        break;
                    }
                }

                $map[$ifIndex] = [
                    'kind' => 'onu',
                    'label' => $label,
                    'card_no' => $card,
                    'pon_no' => $pon,
                    'onu_index' => $onuIdx,
                    'pon_ifindex' => $ponIf,
                ];
            }
        }

        foreach ($map as $idx => &$row) {
            if ($row['kind'] !== 'onu' || $row['pon_ifindex'] !== null) {
                continue;
            }
            foreach ($map as $ponIdx => $ponRow) {
                if ($ponRow['kind'] === 'pon'
                    && $ponRow['card_no'] === $row['card_no']
                    && $ponRow['pon_no'] === $row['pon_no']) {
                    $row['pon_ifindex'] = $ponIdx;
                    break;
                }
            }
        }
        unset($row);

        return $map;
    }

    /**
     * @return array<int, string>
     */
    /**
     * @return array<int, string>
     */
    private function walkStringsByIfIndex(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $out = [];
        try {
            foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
                $suffix = SnmpClient::suffixFromOidKey($key, $oid);
                if ($suffix === null || ! ctype_digit($suffix)) {
                    continue;
                }
                $text = $this->cleanSnmpValue((string) $value);
                if ($text !== '') {
                    $out[(int) $suffix] = $text;
                }
            }
        } catch (\Throwable $e) {
            Log::debug('bdcom.onu_desc_walk_failed', ['oid' => $oid, 'error' => $e->getMessage()]);
        }

        return $out;
    }

    private function walkMacByIfIndex(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $out = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null || ! ctype_digit($suffix)) {
                continue;
            }
            $mac = $this->parseMac($value);
            if ($mac !== null) {
                $out[(int) $suffix] = $mac;
            }
        }

        return $out;
    }

    /**
     * BDCOM opModuleRxPower / opModuleTxPower — unit 0.1 dBm (divide in OpticalPowerNormalizer only).
     *
     * @return array<int, float>
     */
    private function walkRawSnmpDbm(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $out = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null || ! ctype_digit($suffix)) {
                continue;
            }
            $numeric = $this->parseSnmpNumber($value);
            if ($numeric !== null) {
                $out[(int) $suffix] = $numeric;
            }
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function walkOnuStatus(string $peer, string $community, string $oid, int $timeoutUs, int $retries): array
    {
        $out = [];
        foreach (SnmpClient::realWalk($peer, $community, $oid, $timeoutUs, $retries) as $key => $value) {
            $suffix = SnmpClient::suffixFromOidKey($key, $oid);
            if ($suffix === null || ! preg_match('/^(\d+)\.(\d+)$/', $suffix, $m)) {
                continue;
            }
            $numeric = $this->parseSnmpNumber($value);
            if ($numeric !== null) {
                $out[$suffix] = (int) $numeric;
            }
        }

        return $out;
    }

    private function cleanSnmpValue(string $raw): string
    {
        $raw = trim($raw);
        $raw = preg_replace('/^[A-Za-z-]+:\s*/', '', $raw) ?? $raw;

        return trim($raw, "\" \t");
    }

    private function parseSnmpNumber(string $raw): ?float
    {
        $clean = $this->cleanSnmpValue($raw);
        if ($clean === '' || ! is_numeric($clean)) {
            return null;
        }

        return (float) $clean;
    }

    private function parseMac(string $raw): ?string
    {
        $colon = MacAddress::fromSnmpValue($raw);

        return $colon !== null ? strtolower($colon) : null;
    }

    private function mapStatusCode(?int $code): string
    {
        return match ($code) {
            0, 1, 5 => 'online',
            2 => 'offline',
            3 => 'offline',
            4 => 'los',
            default => 'unknown',
        };
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, int>  $stats
     */
    private function upsertOnu(Device $olt, array $row, array &$stats): ?Device
    {
        $mac = (string) $row['mac'];
        $macCompact = strtoupper(str_replace(':', '', $mac));

        $onu = Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->where(function ($q) use ($mac, $macCompact, $row): void {
                $q->where('mac_address', $mac)
                    ->orWhere('serial_number', $macCompact)
                    ->orWhere('onu_external_id', (string) ($row['if_index'] ?? ''));
            })
            ->first();

        $isNew = $onu === null;
        if ($isNew) {
            $onu = new Device([
                'tenant_id' => $olt->tenant_id,
                'type' => 'onu',
                'olt_id' => $olt->id,
                'serial_number' => $macCompact,
                'status' => 'assigned',
            ]);
            $stats['created']++;
        } else {
            $stats['updated']++;
        }

        $customer = $this->matchCustomerByMac((int) $olt->tenant_id, $mac, $macCompact);
        $description = trim((string) ($row['description'] ?? ''));
        if ($customer === null && $description !== ''
            && ! BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel($description)) {
            $customer = Customer::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $olt->tenant_id)
                ->where('customer_code', $description)
                ->first();

            if ($customer === null) {
                $resolved = CustomerPppLoginResolver::resolve((int) $olt->tenant_id, $description);
                if ($resolved !== null && CustomerPppLoginResolver::normalize($description) === CustomerPppLoginResolver::normalize($resolved->pppLoginName())) {
                    $customer = $resolved;
                }
            }
        }

        $meta = is_array($onu->meta) ? $onu->meta : [];
        $meta['bdcom_if_index'] = $row['if_index'];
        $meta['bdcom_status'] = $row['bdcom_status'];
        $meta['bdcom_label'] = $row['label'];
        $meta['last_bdcom_sync'] = now()->toIso8601String();
        if ($description !== '') {
            $meta['bdcom_description'] = $description;
        }

        $login = $customer?->pppLoginName() ?? '';
        if ($login === '') {
            $login = BdcomOnuDescriptionHeuristic::sanitizePppLoginHint($description, (int) $olt->tenant_id) ?? '';
        }
        if ($login !== '') {
            $meta['ppp_login'] = $login;
        } elseif (isset($meta['ppp_login']) && BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel((string) $meta['ppp_login'])) {
            unset($meta['ppp_login']);
        }

        $externalId = $onu->onu_external_id;
        if (blank($externalId) || BdcomOnuDescriptionHeuristic::isOltPlaceholderLabel((string) $externalId)) {
            $externalId = $login !== '' ? $login : (string) $row['label'];
        }

        $onu->forceFill([
            'mac_address' => $mac,
            'display_name' => (string) $row['label'],
            'card_no' => $row['card_no'],
            'pon_no' => $row['pon_no'],
            'onu_index' => $row['onu_index'],
            'onu_oper_status' => $row['oper_status'],
            'customer_id' => $onu->customer_id ?? $customer?->id,
            'onu_external_id' => $externalId,
            'meta' => $meta,
        ])->save();

        if ($row['rx_dbm'] !== null || $row['tx_dbm'] !== null) {
            $this->opticalPipeline->ingest($onu->fresh(), [
                'rx_raw' => $row['rx_dbm'],
                'tx_raw' => $row['tx_dbm'],
                'oper_status' => $row['oper_status'],
                'vendor_profile' => 'bdcom_epon',
                'source' => 'bdcom_snmp',
            ]);
        }

        if ($customer !== null && $onu->customer_id === $customer->id) {
            $stats['linked']++;
        }

        return $onu;
    }

    private function matchCustomerByMac(int $tenantId, string $mac, string $macCompact): ?Customer
    {
        return \App\Services\Optical\CustomerOnuMatcher::matchCustomerByOnuMac($tenantId, $mac, $macCompact);
    }

    /**
     * @param  list<array<string, mixed>>  $discovered
     */
    public function deleteOfflineOnus(Device $olt, array $discovered): int
    {
        $offlineMacs = collect($discovered)
            ->filter(fn (array $r): bool => in_array($r['oper_status'], ['offline', 'los'], true))
            ->pluck('mac')
            ->all();

        if ($offlineMacs === []) {
            return 0;
        }

        return Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->whereIn('mac_address', $offlineMacs)
            ->delete();
    }

    /**
     * Delete ONU inventory rows not seen on OLT during last sync (stale placeholders).
     */
    public function purgeAutoProvisionedPlaceholders(Device $olt): int
    {
        return Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->where('serial_number', 'like', 'SUB-%')
            ->where(function ($q): void {
                $q->whereNull('meta->last_bdcom_sync')
                    ->orWhereRaw("(meta->>'last_bdcom_sync') IS NULL");
            })
            ->delete();
    }

    public function deleteStaleInventory(Device $olt, int $olderThanHours = 24): int
    {
        return Device::query()
            ->withoutGlobalScopes()
            ->where('olt_id', $olt->id)
            ->where('type', 'onu')
            ->where(function ($q): void {
                $q->whereNull('meta->last_bdcom_sync')
                    ->orWhere('onu_oper_status', 'offline')
                    ->orWhere('onu_oper_status', 'los')
                    ->orWhere('onu_oper_status', 'unknown');
            })
            ->where('updated_at', '<', now()->subHours($olderThanHours))
            ->delete();
    }
}
