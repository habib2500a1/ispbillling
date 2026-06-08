<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\IspOs\FaultManagementService;
use App\Services\IspOs\RootCauseAnalysisService;
use App\Support\Rbac\StaffCapability;
use Filament\Pages\Page;

class FaultManagementHub extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static string $view = 'filament.pages.fault-management-hub';

    protected static ?string $navigationLabel = 'Fault center';

    protected static ?string $title = 'Fault management';

    protected static ?string $navigationGroup = 'Network';

    protected static ?string $slug = 'fault-center';

    protected static bool $shouldRegisterNavigation = false;

    public function getExtraBodyAttributes(): array
    {
        return ['class' => 'isp-os-module'];
    }

    /**
     * @return array{summary: array<string, int>, faults: list<array<string, mixed>>}
     */
    public function getFaultPayload(): array
    {
        return app(FaultManagementService::class)->payload();
    }

    /**
     * @return list<array{root_cause: string, confidence: float, message: string, tone: string}>
     */
    public function getRootCauses(): array
    {
        return app(RootCauseAnalysisService::class)->analyze();
    }

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canNetwork();
    }
}
