<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\OltResource;
use App\Models\Device;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class OltHub extends Page
{
    use HidesHubNavigation;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static string $view = 'filament.pages.olt-hub';

    protected static ?string $slug = 'olt-hub';

    protected static ?string $title = 'OLT';

    /**
     * @return array{olts: int, onus: int, onus_online: int, onus_with_rx: int}
     */
    public function getStats(): array
    {
        $onus = Device::query()->where('type', 'onu');

        return [
            'olts' => Device::query()->where('type', 'olt')->count(),
            'onus' => (clone $onus)->count(),
            'onus_online' => (clone $onus)->whereIn('onu_oper_status', ['online', 'active', 'up'])->count(),
            'onus_with_rx' => (clone $onus)->whereNotNull('rx_power_dbm')->count(),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && StaffCapability::for($user)->canOlt();
    }
}
