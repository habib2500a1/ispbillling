<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Resources\AreaResource;
use App\Filament\Resources\DistrictResource;
use App\Filament\Resources\MikrotikServerResource;
use App\Filament\Resources\PackageResource;
use App\Filament\Resources\PopBoxResource;
use App\Filament\Resources\ZoneResource;
use App\Models\Area;
use App\Models\District;
use App\Models\MikrotikServer;
use App\Models\Package;
use App\Models\Zone;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class IspSetupCenter extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.isp-setup-center';

    protected static ?string $navigationLabel = 'ISP setup';

    protected static ?string $title = 'ISP Setup Center';

    protected static ?string $slug = 'isp-setup';

    protected static ?string $navigationGroup = 'Configuration';

    protected static ?int $navigationSort = 1;

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        return [
            'kpis' => [
                ['label' => 'Areas', 'value' => Area::query()->count()],
                ['label' => 'Zones', 'value' => Zone::query()->count()],
                ['label' => 'Packages', 'value' => Package::query()->where('is_active', true)->count()],
                ['label' => 'MikroTik', 'value' => MikrotikServer::query()->where('is_enabled', true)->count()],
                ['label' => 'Districts', 'value' => District::query()->count()],
            ],
            'sections' => $this->linkSections(),
        ];
    }

    /**
     * @return list<array{title: string, links: list<array{label: string, description: string, url: string, icon: string}>}>
     */
    private function linkSections(): array
    {
        return [
            [
                'title' => 'Coverage & packages',
                'links' => [
                    ['label' => 'Areas', 'description' => 'Top-level coverage regions', 'url' => AreaResource::getUrl('index'), 'icon' => 'map'],
                    ['label' => 'Zones & subzones', 'description' => 'Area → zone → subzone tree', 'url' => ZoneResource::getUrl('index'), 'icon' => 'map-pin'],
                    ['label' => 'POP / boxes', 'description' => 'Street cabinets & POP sites', 'url' => PopBoxResource::getUrl('index'), 'icon' => 'cube'],
                    ['label' => 'Packages', 'description' => 'Speed plans & monthly price', 'url' => PackageResource::getUrl('index'), 'icon' => 'rectangle-stack'],
                ],
            ],
            [
                'title' => 'Address reference (Bangladesh)',
                'links' => [
                    ['label' => 'Districts & upazilas', 'description' => 'Subscriber district / thana dropdowns', 'url' => DistrictResource::getUrl('index'), 'icon' => 'globe-asia-australia'],
                ],
            ],
            [
                'title' => 'Network & import',
                'links' => [
                    ['label' => 'MikroTik servers', 'description' => 'Router API · PPP profiles', 'url' => MikrotikServerResource::getUrl('index'), 'icon' => 'server'],
                    ['label' => 'Network settings', 'description' => 'Auto suspend · grace · expiry', 'url' => ManageNetworkSettings::getUrl(), 'icon' => 'cog-6-tooth'],
                    ['label' => 'Import from MikroTik', 'description' => 'Pull PPP secrets from router', 'url' => ImportFromMikrotikPage::getUrl(), 'icon' => 'arrow-down-tray'],
                ],
            ],
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        if ($user->hasRole(StaffCapability::FULL_ACCESS_ROLES)) {
            return true;
        }

        $cap = StaffCapability::for($user);

        return $cap->canNetwork() || $cap->can('packages.view') || $cap->can('packages.manage');
    }
}
