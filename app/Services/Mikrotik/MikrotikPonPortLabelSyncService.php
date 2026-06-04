<?php

namespace App\Services\Mikrotik;

use App\Models\Device;
use App\Models\OltPort;
use App\Support\MikrotikVlanParser;
use App\Support\SubscriberNetworkLabels;

/**
 * Copy RouterOS interface names from subscriber meta onto OLT PON port labels.
 */
final class MikrotikPonPortLabelSyncService
{
    /**
     * @return array{ports_updated: int, ports_seen: int}
     */
    public function syncTenant(int $tenantId): array
    {
        /** @var array<int, array<string, int>> $byPortId */
        $byPortId = [];

        Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'onu')
            ->whereNotNull('customer_id')
            ->whereNotNull('olt_id')
            ->whereNotNull('card_no')
            ->whereNotNull('pon_no')
            ->with(['customer:id,meta'])
            ->chunkById(500, function ($onus) use (&$byPortId, $tenantId): void {
                foreach ($onus as $onu) {
                    $iface = SubscriberNetworkLabels::mikrotikInterfaceName($onu->customer);
                    if ($iface === null) {
                        continue;
                    }

                    $port = $this->resolveOltPort($tenantId, $onu);
                    if ($port === null) {
                        continue;
                    }

                    $ponIndex = (int) $onu->pon_no;
                    if ($ponIndex > 0 && ! $this->interfaceMatchesPon($iface, $ponIndex)) {
                        continue;
                    }

                    $byPortId[$port->id][$iface] = ($byPortId[$port->id][$iface] ?? 0) + 1;
                }
            });

        $updated = 0;

        foreach ($byPortId as $portId => $counts) {
            if ($counts === []) {
                continue;
            }

            arsort($counts);
            $iface = (string) array_key_first($counts);

            $port = OltPort::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find($portId);

            if ($port === null) {
                continue;
            }

            if ($this->applyInterfaceLabel($port, $iface)) {
                $updated++;
            }
        }

        return [
            'ports_updated' => $updated,
            'ports_seen' => count($byPortId),
        ];
    }

    private function resolveOltPort(int $tenantId, Device $onu): ?OltPort
    {
        if ($onu->olt_port_id !== null) {
            return OltPort::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->find((int) $onu->olt_port_id);
        }

        if ($onu->olt_id === null || $onu->card_no === null || $onu->pon_no === null) {
            return null;
        }

        return OltPort::query()
            ->withoutGlobalScopes()
            ->firstOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'device_id' => (int) $onu->olt_id,
                    'card_index' => (int) $onu->card_no,
                    'pon_index' => (int) $onu->pon_no,
                ],
                [
                    'admin_status' => 'unknown',
                    'oper_status' => 'unknown',
                ],
            );
    }

    private function interfaceMatchesPon(string $interface, int $ponIndex): bool
    {
        $fromName = MikrotikVlanParser::ponIndexFromOltInterfaceLabel($interface);

        return $fromName === null || $fromName === $ponIndex;
    }

    public function applyInterfaceLabel(OltPort $port, string $interfaceName): bool
    {
        $interfaceName = trim($interfaceName);
        if ($interfaceName === '' || SubscriberNetworkLabels::isTechnicalIndexOnly($interfaceName)) {
            return false;
        }

        $current = trim((string) ($port->label ?? ''));
        $meta = is_array($port->meta) ? $port->meta : [];
        $meta['mikrotik_interface'] = $interfaceName;
        $meta['mikrotik_interface_synced_at'] = now()->toIso8601String();

        $shouldReplaceLabel = $current === ''
            || SubscriberNetworkLabels::isTechnicalIndexOnly($current)
            || $current === ($port->card_index.'/'.$port->pon_index);

        if (! $shouldReplaceLabel && $current === $interfaceName) {
            if (($meta['mikrotik_interface'] ?? null) === $interfaceName) {
                return false;
            }
            $port->forceFill(['meta' => $meta])->saveQuietly();

            return false;
        }

        if (! $shouldReplaceLabel) {
            $port->forceFill(['meta' => $meta])->saveQuietly();

            return false;
        }

        $port->forceFill([
            'label' => $interfaceName,
            'meta' => $meta,
        ])->saveQuietly();

        return true;
    }
}
