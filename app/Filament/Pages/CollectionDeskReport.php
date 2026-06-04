<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ScopesStaffCollectorReports;
use App\Services\Billing\CollectionDeskReportService;
use App\Services\Billing\CollectionReportCsvExporter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionDeskReport extends Page
{
    use ScopesStaffCollectorReports;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.collection-desk-report';

    protected static ?string $navigationLabel = 'Collection report';

    protected static ?string $title = 'Collection report';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 40;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $collectorId = null;

    public string $search = '';

    public ?int $customerId = null;

    /** all | desk | legacy_portal */
    public string $sourceFilter = '';

    /** all | cash | bkash | bank | … */
    public string $methodFilter = 'all';

    public function mount(): void
    {
        $preset = request()->string('preset')->toString();
        if ($preset === 'month') {
            $this->setDatePreset('month');
        } elseif ($preset === 'week') {
            $this->setDatePreset('week');
        } else {
            $this->setDatePreset('today');
        }

        $filterCustomer = request()->integer('customer');
        if ($filterCustomer > 0) {
            $this->customerId = $filterCustomer;
        }

        if ($this->sourceFilter === '') {
            $this->sourceFilter = 'all';
        }

        $this->mountStaffCollectorReportScope();
    }

    public function setDatePreset(string $preset): void
    {
        if ($preset === 'yesterday') {
            $day = now()->subDay();
            $this->dateFrom = $day->toDateString();
            $this->dateTo = $day->toDateString();

            return;
        }

        if ($preset === 'week') {
            $this->dateFrom = now()->startOfWeek()->toDateString();
            $this->dateTo = now()->toDateString();

            return;
        }

        if ($preset === 'month') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
            $this->dateTo = now()->toDateString();

            return;
        }

        if ($preset === 'last7') {
            $this->dateFrom = now()->subDays(6)->toDateString();
            $this->dateTo = now()->toDateString();

            return;
        }

        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function activeDatePreset(): ?string
    {
        if ($this->dateFrom === now()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'today';
        }

        $yesterday = now()->subDay()->toDateString();
        if ($this->dateFrom === $yesterday && $this->dateTo === $yesterday) {
            return 'yesterday';
        }

        if ($this->dateFrom === now()->subDays(6)->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'last7';
        }

        if ($this->dateFrom === now()->startOfWeek()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'week';
        }

        if ($this->dateFrom === now()->startOfMonth()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'month';
        }

        return null;
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $capability = \App\Support\Rbac\StaffCapability::for($user);

        return $capability->canPayments() || $capability->canBilling();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        return app(CollectionDeskReportService::class)->report(
            Carbon::parse($this->dateFrom ?: now()->toDateString()),
            Carbon::parse($this->dateTo ?: now()->toDateString()),
            $this->effectiveReportCollectorId(),
            $this->search ?: null,
            null,
            $this->customerId ?: null,
            $this->sourceFilter ?: null,
            $this->methodFilter !== 'all' ? $this->methodFilter : null,
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function getCollectorOptions(): array
    {
        return app(CollectionDeskReportService::class)
            ->collectorsForFilter()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    public function exportCsv(): StreamedResponse
    {
        return app(CollectionReportCsvExporter::class)->download(
            Carbon::parse($this->dateFrom),
            Carbon::parse($this->dateTo),
            $this->effectiveReportCollectorId(),
            $this->search ?: null,
            $this->customerId ?: null,
            $this->sourceFilter ?: null,
            $this->methodFilter !== 'all' ? $this->methodFilter : null,
        );
    }
}
