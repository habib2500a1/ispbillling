<?php

namespace App\Filament\Resources\OltResource\Pages\Concerns;

use App\Filament\Pages\OltHub;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Resources\OltResource;
use App\Models\Device;

/**
 * Premium OLT list chrome (UI only).
 */
trait UsesOltListLayout
{
    /**
     * @return array{total: int, online: int, offline: int, onus: int}
     */
    public function getOltFleetStats(): array
    {
        $base = Device::query()->where('type', 'olt');
        $total = (int) (clone $base)->count();
        $online = (int) (clone $base)->where('status', 'active')->count();

        return [
            'total' => $total,
            'online' => $online,
            'offline' => max(0, $total - $online),
            'onus' => (int) Device::query()->where('type', 'onu')->count(),
        ];
    }

    /**
     * @return list<array{url: string, label: string, icon: string, active?: bool}>
     */
    public function getOltDockLinks(): array
    {
        return [
            ['url' => OltHub::getUrl(), 'label' => 'Center', 'icon' => 'heroicon-o-squares-2x2'],
            ['url' => OltResource::getUrl('index'), 'label' => 'OLTs', 'icon' => 'heroicon-o-server-stack', 'active' => true],
            ['url' => OpticalMonitoringHub::getUrl(), 'label' => 'Optical', 'icon' => 'heroicon-o-light-bulb'],
            ['url' => OltResource::getUrl('create'), 'label' => 'Add', 'icon' => 'heroicon-o-plus'],
        ];
    }
}
