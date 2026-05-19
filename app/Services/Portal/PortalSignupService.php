<?php

namespace App\Services\Portal;

use App\Models\Area;
use App\Models\Package;
use App\Models\SalesLead;
use App\Models\Zone;

final class PortalSignupService
{
    /**
     * @param  array{name: string, phone: string, email?: string, address?: string, area_id?: int, zone_id?: int, package_id?: int, notes?: string}  $data
     */
    public function submit(array $data): SalesLead
    {
        $package = isset($data['package_id']) ? Package::query()->find($data['package_id']) : null;

        return SalesLead::query()->create([
            'name' => $data['name'],
            'phone' => preg_replace('/\D+/', '', $data['phone']) ?: $data['phone'],
            'email' => $data['email'] ?? null,
            'address' => $data['address'] ?? null,
            'area_id' => $data['area_id'] ?? null,
            'zone_id' => $data['zone_id'] ?? null,
            'package_id' => $package?->id,
            'estimated_mrr' => $package?->price_monthly,
            'source' => 'website',
            'status' => SalesLead::STATUS_NEW,
            'notes' => $data['notes'] ?? 'Submitted via public portal signup.',
        ]);
    }

    /**
     * @return array<int, string>
     */
    public function areaOptions(): array
    {
        return Area::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<int, string>
     */
    public function packageOptions(): array
    {
        return Package::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function zonesForArea(?int $areaId): array
    {
        if ($areaId === null) {
            return [];
        }

        return Zone::query()
            ->where('area_id', $areaId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }
}
