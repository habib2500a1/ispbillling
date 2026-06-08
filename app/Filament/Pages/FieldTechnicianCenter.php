<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Filament\Pages\FiberPlantMap;
use App\Filament\Pages\FaultManagementHub;
use App\Filament\Pages\IspOsHub;
use App\Filament\Pages\OpticalMonitoringHub;
use App\Filament\Pages\OltHub;
use App\Filament\Pages\SupportHub;
use App\Filament\Resources\StoreDeviceLoanResource;
use App\Filament\Resources\SupportTicketResource;
use App\Services\IspOs\FieldTechnicianIntelligenceService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class FieldTechnicianCenter extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string $view = 'filament.pages.field-technician-center';

    protected static ?string $navigationLabel = 'Field technicians';

    protected static ?string $title = '';

    protected static ?string $navigationGroup = 'Support';

    protected static ?string $slug = 'field-technicians';

    protected static bool $shouldRegisterNavigation = false;

    public string $searchQuery = '';

    public string $activeTaskTab = 'assigned';

    /** @var array<string, mixed> */
    public array $fieldIntel = [];

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public function mount(): void
    {
        $this->refreshIntel();
    }

    public function getTitle(): string
    {
        return '';
    }

    public function getHeading(): string
    {
        return '';
    }

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'isp-os-module field-ops-module'];
    }

    public function refreshIntel(): void
    {
        $this->fieldIntel = app(FieldTechnicianIntelligenceService::class)->metrics();
    }

    public function updatedSearchQuery(): void
    {
        $this->searchResults = app(FieldTechnicianIntelligenceService::class)
            ->search($this->searchQuery, (int) auth()->id());
    }

    public function setTaskTab(string $tab): void
    {
        $this->activeTaskTab = $tab;
    }

    /**
     * @return array<string, mixed>
     */
    public function loadCustomer360(int $ticketId): array
    {
        return app(FieldTechnicianIntelligenceService::class)->customerBundle($ticketId) ?? [];
    }

    public function hubLinks(): array
    {
        return [
            ['label' => 'GIS map', 'desc' => 'Customers · fiber · splitters', 'url' => FiberPlantMap::getUrl(), 'icon' => 'heroicon-o-map'],
            ['label' => 'Fault center', 'desc' => 'Outages · signal alerts', 'url' => FaultManagementHub::getUrl(), 'icon' => 'heroicon-o-bolt'],
            ['label' => 'ONU lookup', 'desc' => 'Signal & registration', 'url' => OpticalMonitoringHub::getUrl(), 'icon' => 'heroicon-o-signal'],
            ['label' => 'OLT lookup', 'desc' => 'PON & chassis health', 'url' => OltHub::getUrl(), 'icon' => 'heroicon-o-server'],
            ['label' => 'Support hub', 'desc' => 'Ticket desk', 'url' => SupportHub::getUrl(), 'icon' => 'heroicon-o-lifebuoy'],
            ['label' => 'Device loans', 'desc' => 'Assigned equipment', 'url' => StoreDeviceLoanResource::getUrl(), 'icon' => 'heroicon-o-cpu-chip'],
            ['label' => 'ISP OS', 'desc' => 'Operations center', 'url' => IspOsHub::getUrl(), 'icon' => 'heroicon-o-squares-2x2'],
            ['label' => 'New ticket', 'desc' => 'Create support ticket', 'url' => SupportTicketResource::getUrl('create'), 'icon' => 'heroicon-o-plus-circle'],
        ];
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canSupport()
            || StaffCapability::for(auth()->user())->canNetwork();
    }
}
