<?php

namespace App\Filament\Pages;

use App\Services\Payments\GatewayReconciliationService;
use Carbon\Carbon;
use Filament\Pages\Page;

class GatewayReconciliationReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-scale';

    protected static string $view = 'filament.pages.gateway-reconciliation-report';

    protected static ?string $navigationLabel = 'Gateway reconciliation';

    protected static ?string $title = 'Payment gateway reconciliation';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 6;

    public string $dateFrom = '';

    public string $dateTo = '';

    public function mount(): void
    {
        $this->dateFrom = now()->subDays(7)->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super-admin', 'isp-admin', 'admin']) ?? false;
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        return app(GatewayReconciliationService::class)->snapshot(
            Carbon::parse($this->dateFrom),
            Carbon::parse($this->dateTo),
        );
    }
}
