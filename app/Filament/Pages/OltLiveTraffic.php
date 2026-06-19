<?php

namespace App\Filament\Pages;

use App\Models\Device;
use App\Services\Network\OltSnmpMonitorService;
use App\Services\Olt\OltTrafficHistoryService;
use App\Support\Rbac\StaffCapability;
use App\Support\TenantResolver;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class OltLiveTraffic extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.olt-live-traffic';

    protected static ?string $navigationLabel = 'OLT live traffic';

    protected static ?string $title = 'OLT live traffic';

    protected static ?string $slug = 'olt-live-traffic';

    protected static bool $shouldRegisterNavigation = false;

    public ?string $filterOlt = null;

    public string $period = '24h';

    public function mount(): void
    {
        $requested = request()->query('filterOlt');
        if ($requested !== null && $requested !== '') {
            $this->filterOlt = (string) $requested;

            return;
        }

        $first = Device::query()
            ->where('tenant_id', TenantResolver::requiredTenantId())
            ->where('type', 'olt')
            ->orderBy('display_name')
            ->value('id');

        $this->filterOlt = $first !== null ? (string) $first : null;
    }

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
     * @return list<array{id: int, label: string}>
     */
    public function getOltOptionsProperty(): array
    {
        return Device::query()
            ->where('tenant_id', TenantResolver::requiredTenantId())
            ->where('type', 'olt')
            ->orderBy('display_name')
            ->get(['id', 'display_name', 'management_ip'])
            ->map(fn (Device $d): array => [
                'id' => $d->id,
                'label' => $d->adminLabel(),
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function getTrafficSeriesProperty(): array
    {
        if (! $this->filterOlt) {
            return app(OltTrafficHistoryService::class)->series(0, $this->period);
        }

        return app(OltTrafficHistoryService::class)->series((int) $this->filterOlt, $this->period);
    }

    public function pollNow(): void
    {
        if (! $this->filterOlt) {
            return;
        }

        $olt = Device::query()->find((int) $this->filterOlt);
        if (! $olt) {
            return;
        }

        try {
            app(OltSnmpMonitorService::class)->pollOlt($olt);
            Notification::make()->title('OLT polled')->body('Traffic sample updated.')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Poll failed')->body($e->getMessage())->danger()->send();
        }
    }
}
