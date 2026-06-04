<?php

namespace App\Services\Olt;

use App\Models\Device;
use App\Models\OltPort;
use App\Models\PonSignalStat;

/**
 * Ensure every OLT has a full PON port catalog (e.g. XE08 = 8 ports) plus any extra slots seen on ONUs.
 */
final class OltPortCatalogService
{
    /**
     * @return array{created: int, total: int}
     */
    public function ensureForTenant(int $tenantId): array
    {
        $created = 0;
        $total = 0;

        Device::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('type', 'olt')
            ->where('status', '!=', 'decommissioned')
            ->orderBy('display_name')
            ->each(function (Device $olt) use (&$created, &$total): void {
                $result = $this->ensureForOlt($olt);
                $created += $result['created'];
                $total += $result['total'];
            });

        return ['created' => $created, 'total' => $total];
    }

    /**
     * @return array{created: int, pruned: int, total: int}
     */
    public function ensureForOlt(Device $olt): array
    {
        $created = 0;
        $catalogSlots = $this->expectedSlots($olt);
        $slots = $catalogSlots;

        foreach ($olt->onus()->withoutGlobalScopes()->get(['card_no', 'pon_no']) as $onu) {
            if ($onu->card_no === null || $onu->pon_no === null) {
                continue;
            }
            $slots[$this->slotKey((int) $onu->card_no, (int) $onu->pon_no)] = [
                'card' => (int) $onu->card_no,
                'pon' => (int) $onu->pon_no,
            ];
        }

        foreach ($slots as $slot) {
            $port = OltPort::query()
                ->withoutGlobalScopes()
                ->firstOrCreate(
                    [
                        'tenant_id' => $olt->tenant_id,
                        'device_id' => $olt->id,
                        'card_index' => $slot['card'],
                        'pon_index' => $slot['pon'],
                    ],
                    [
                        'admin_status' => 'unknown',
                        'oper_status' => 'unknown',
                    ],
                );

            if ($port->wasRecentlyCreated) {
                $created++;
            }
        }

        $pruned = $this->pruneStaleCatalogPorts($olt, $catalogSlots);

        return ['created' => $created, 'pruned' => $pruned, 'total' => count($slots)];
    }

    /**
     * Drop ghost ports from an old/default catalog (e.g. Aveis XE08 had both C0 and C1 rows).
     *
     * @param  array<string, array{card: int, pon: int}>  $catalogSlots
     */
    private function pruneStaleCatalogPorts(Device $olt, array $catalogSlots): int
    {
        $pruned = 0;

        OltPort::query()
            ->withoutGlobalScopes()
            ->where('device_id', $olt->id)
            ->each(function (OltPort $port) use ($olt, $catalogSlots, &$pruned): void {
                $key = $this->slotKey((int) $port->card_index, (int) $port->pon_index);
                if (isset($catalogSlots[$key])) {
                    return;
                }

                $hasOnus = Device::query()
                    ->withoutGlobalScopes()
                    ->where('olt_id', $olt->id)
                    ->where('type', 'onu')
                    ->where('card_no', $port->card_index)
                    ->where('pon_no', $port->pon_index)
                    ->exists();

                if ($hasOnus) {
                    return;
                }

                PonSignalStat::query()
                    ->withoutGlobalScopes()
                    ->where('olt_id', $olt->id)
                    ->where('card_no', $port->card_index)
                    ->where('pon_no', $port->pon_index)
                    ->delete();

                $port->delete();
                $pruned++;
            });

        return $pruned;
    }

    /**
     * @return array<string, array{card: int, pon: int}>
     */
    private function expectedSlots(Device $olt): array
    {
        $layout = config('gpon.olt_pon_catalog.'.$this->vendorKey($olt))
            ?? config('gpon.olt_pon_catalog.default', ['cards' => [0], 'pon_min' => 1, 'pon_max' => 8]);

        $cards = $layout['cards'] ?? [0];
        $ponMin = (int) ($layout['pon_min'] ?? 1);
        $ponMax = (int) ($layout['pon_max'] ?? 8);

        $slots = [];
        foreach ($cards as $card) {
            $card = (int) $card;
            for ($pon = $ponMin; $pon <= $ponMax; $pon++) {
                $slots[$this->slotKey($card, $pon)] = ['card' => $card, 'pon' => $pon];
            }
        }

        return $slots;
    }

    private function vendorKey(Device $olt): string
    {
        $driver = strtolower((string) ($olt->olt_driver ?? $olt->vendor ?? ''));

        foreach (array_keys(config('gpon.olt_pon_catalog', [])) as $key) {
            if ($key !== 'default' && str_contains($driver, $key)) {
                return $key;
            }
        }

        return 'default';
    }

    private function slotKey(int $card, int $pon): string
    {
        return $card.'-'.$pon;
    }
}
