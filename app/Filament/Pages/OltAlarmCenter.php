<?php

namespace App\Filament\Pages;

use App\Services\Olt\OltAlarmCenterService;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Filament\Pages\Page;

class OltAlarmCenter extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static string $view = 'filament.pages.olt-alarm-center';

    protected static ?string $navigationLabel = 'Alarm Center';

    protected static ?string $title = 'OLT Alarm Center';

    protected static ?string $slug = 'olt-alarm-center';

    protected static bool $shouldRegisterNavigation = false;

    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-olt-module',
        ];
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canOlt();
    }

    /**
     * @return array{summary: array<string, int>, alarms: list<array<string, mixed>>}
     */
    public function getAlarmPayload(): array
    {
        return app(OltAlarmCenterService::class)->snapshot(TenantResolver::requiredTenantId());
    }
}
