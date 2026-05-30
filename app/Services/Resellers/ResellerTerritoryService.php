<?php

namespace App\Services\Resellers;

use App\Models\Reseller;
use App\Models\ResellerTerritory;
use Illuminate\Validation\ValidationException;

final class ResellerTerritoryService
{
    public function hasTerritoryRestrictions(Reseller $reseller): bool
    {
        return ResellerTerritory::query()
            ->where('reseller_id', $reseller->id)
            ->exists();
    }

    /**
     * @return list<int>
     */
    public function allowedAreaIds(Reseller $reseller): array
    {
        return ResellerTerritory::query()
            ->where('reseller_id', $reseller->id)
            ->whereNotNull('area_id')
            ->pluck('area_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<int>
     */
    public function allowedZoneIds(Reseller $reseller): array
    {
        return ResellerTerritory::query()
            ->where('reseller_id', $reseller->id)
            ->whereNotNull('zone_id')
            ->pluck('zone_id')
            ->unique()
            ->values()
            ->all();
    }

    public function assertCustomerLocationAllowed(Reseller $reseller, ?int $areaId, ?int $zoneId): void
    {
        if (! $this->hasTerritoryRestrictions($reseller)) {
            return;
        }

        $territories = ResellerTerritory::query()
            ->where('reseller_id', $reseller->id)
            ->get(['area_id', 'zone_id', 'subzone_id']);

        foreach ($territories as $territory) {
            if ($territory->zone_id !== null && $zoneId !== null && (int) $territory->zone_id === $zoneId) {
                return;
            }
            if ($territory->area_id !== null && $areaId !== null && (int) $territory->area_id === $areaId) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'area_id' => 'This area is not assigned to your partner account. Contact admin to update territory.',
        ]);
    }

    /**
     * Filter areas/zones for portal forms based on assigned territories.
     *
     * @param  \Illuminate\Support\Collection<int, \App\Models\Area>  $areas
     * @param  \Illuminate\Support\Collection<int, \App\Models\Zone>  $zones
     * @return array{areas: \Illuminate\Support\Collection, zones: \Illuminate\Support\Collection}
     */
    public function filterFormOptions(Reseller $reseller, $areas, $zones): array
    {
        if (! $this->hasTerritoryRestrictions($reseller)) {
            return ['areas' => $areas, 'zones' => $zones];
        }

        $allowedAreas = $this->allowedAreaIds($reseller);
        $allowedZones = $this->allowedZoneIds($reseller);

        return [
            'areas' => $areas->filter(fn ($a) => in_array((int) $a->id, $allowedAreas, true)),
            'zones' => $zones->filter(fn ($z) => in_array((int) $z->id, $allowedZones, true)
                || ($z->area_id && in_array((int) $z->area_id, $allowedAreas, true))),
        ];
    }
}
