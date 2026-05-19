<?php

namespace App\Filament\Pages;

use App\Services\Billing\CollectionDeskReportService;
use App\Services\Billing\CollectionReportCsvExporter;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionDeskReport extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static string $view = 'filament.pages.collection-desk-report';

    protected static ?string $navigationLabel = 'Collection report';

    protected static ?string $title = 'Collection report';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 40;

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $collectorId = null;

    public string $search = '';

    public ?int $customerId = null;

    public function mount(): void
    {
        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
        $filterCustomer = request()->integer('customer');
        if ($filterCustomer > 0) {
            $this->customerId = $filterCustomer;
        }
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

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'admin', 'cashier', 'branch-manager'])
            || $user->can('payments.view') || $user->can('billing.view');
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
            $this->collectorId ?: null,
            $this->search ?: null,
            null,
            $this->customerId ?: null,
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
            $this->collectorId ?: null,
            $this->search ?: null,
            $this->customerId ?: null,
        );
    }
}
