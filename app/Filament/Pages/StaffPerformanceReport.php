<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ScopesStaffCollectorReports;
use App\Services\Import\LegacyPortalDashboardSummaryProvider;
use App\Services\Reports\StaffPerformanceReportService;
use App\Support\BillingPortalLabel;
use App\Support\LegacyPortalPassword;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

class StaffPerformanceReport extends Page
{
    use ScopesStaffCollectorReports;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static string $view = 'filament.pages.staff-performance-report';

    protected static ?string $navigationLabel = 'Staff performance';

    protected static ?string $title = 'Staff collection & new lines';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 42;

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $collectorId = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $capability = \App\Support\Rbac\StaffCapability::for($user);

        return $capability->canPayments() || $capability->canBilling();
    }

    public function mount(): void
    {
        $preset = request()->string('preset')->toString();
        if (in_array($preset, ['today', 'week', 'month'], true)) {
            $this->setDatePreset($preset);
        } else {
            $this->setDatePreset('month');
        }

        $this->mountStaffCollectorReportScope();
    }

    public function setDatePreset(string $preset): void
    {
        if ($preset === 'today') {
            $this->dateFrom = now()->toDateString();
            $this->dateTo = now()->toDateString();

            return;
        }

        if ($preset === 'week') {
            $this->dateFrom = now()->startOfWeek()->toDateString();
            $this->dateTo = now()->toDateString();

            return;
        }

        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function activeDatePreset(): ?string
    {
        if ($this->dateFrom === now()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'today';
        }

        if ($this->dateFrom === now()->startOfWeek()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'week';
        }

        if ($this->dateFrom === now()->startOfMonth()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'month';
        }

        return null;
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $from = Carbon::parse($this->dateFrom ?: now()->toDateString())->startOfDay();
        $to = Carbon::parse($this->dateTo ?: now()->toDateString())->endOfDay();
        $scoped = $this->effectiveReportCollectorId();
        $service = app(StaffPerformanceReportService::class);

        $collection = $service->collectionSummary($tenantId, $from, $to, $scoped);
        $newLines = $service->newLinesSummary($tenantId, $from, $to, $scoped);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'collection' => $collection,
            'new_lines' => $newLines,
            'legacy_portal' => app(LegacyPortalDashboardSummaryProvider::class)->tenantUsesLegacyPortal($tenantId),
        ];
    }

    public function syncLegacyCollections(): void
    {
        if (LegacyPortalPassword::resolve() === '') {
            Notification::make()
                ->title('Legacy portal password not configured')
                ->danger()
                ->send();

            return;
        }

        Artisan::queue('isp:sync-legacy-portal-collections', array_filter([
            '--void-orphans' => (bool) config('legacy_portal.sync_collections_void_orphans', true),
            '--password' => LegacyPortalPassword::resolve(),
        ]));

        $tenantId = TenantResolver::requiredTenantId();
        Cache::forget('dashboard:today-snapshot:'.$tenantId);
        Cache::forget('dashboard:snapshot:'.$tenantId);

        Notification::make()
            ->title('Syncing collections from '.BillingPortalLabel::name())
            ->body('Dashboard totals refresh in about a minute.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        $actions = [];

        if (app(LegacyPortalDashboardSummaryProvider::class)->tenantUsesLegacyPortal(TenantResolver::requiredTenantId())) {
            $actions[] = Action::make('sync_legacy')
                ->label('Sync from '.BillingPortalLabel::name())
                ->icon('heroicon-o-arrow-path')
                ->action(fn () => $this->syncLegacyCollections());
        }

        $actions[] = Action::make('collection_report')
            ->label('Full collection report')
            ->icon('heroicon-o-chart-bar')
            ->url(CollectionDeskReport::getUrl(['preset' => $this->activeDatePreset() ?? 'month']));

        return $actions;
    }
}
