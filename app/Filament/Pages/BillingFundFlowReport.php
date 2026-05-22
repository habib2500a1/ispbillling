<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Services\Billing\BillingFundFlowCsvExporter;
use App\Services\Billing\BillingFundFlowService;
use App\Support\CompanyBranding;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingFundFlowReport extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';

    protected static string $view = 'filament.pages.billing-fund-flow-report';

    protected static ?string $navigationLabel = 'Bill money trail';

    protected static ?string $title = 'Bill collection — where money goes';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 8;

    public string $dateFrom = '';

    public string $dateTo = '';

    public ?int $collectorId = null;

    public string $search = '';

    public bool $includeCompanyExpenses = false;

    public function mount(): void
    {
        if (request()->filled('dateFrom')) {
            $this->dateFrom = request()->string('dateFrom')->toString();
            $this->dateTo = request()->string('dateTo')->toString() ?: $this->dateFrom;
        } else {
            $this->setDatePreset('today');
        }

        if (request()->filled('collectorId')) {
            $this->collectorId = request()->integer('collectorId') ?: null;
        }

        if (request()->filled('search')) {
            $this->search = request()->string('search')->toString();
        }

        if (request()->boolean('includeCompanyExpenses') && $this->canSeeCompanyExpenses()) {
            $this->includeCompanyExpenses = true;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
            Action::make('print')
                ->label('Print / PDF')
                ->icon('heroicon-o-printer')
                ->url(fn (): string => $this->printUrl())
                ->openUrlInNewTab(),
        ];
    }

    public function printUrl(): string
    {
        $query = array_filter([
            'print' => 1,
            'dateFrom' => $this->dateFrom ?: now()->toDateString(),
            'dateTo' => $this->dateTo ?: now()->toDateString(),
            'collectorId' => $this->collectorId,
            'search' => $this->search !== '' ? $this->search : null,
            'includeCompanyExpenses' => $this->includeCompanyExpenses ? 1 : null,
        ], fn ($v) => $v !== null && $v !== '');

        return static::getUrl($query);
    }

    public function exportCsv(): StreamedResponse
    {
        $user = auth()->user();
        $capability = $user ? \App\Support\Rbac\StaffCapability::for($user) : null;
        $includeVendor = $this->includeCompanyExpenses
            && $capability !== null
            && $capability->canAccounting();

        return app(BillingFundFlowCsvExporter::class)->download(
            Carbon::parse($this->dateFrom),
            Carbon::parse($this->dateTo),
            $this->collectorId ?: null,
            $this->search ?: null,
            $includeVendor,
        );
    }

    public function companyName(): string
    {
        return CompanyBranding::name();
    }

    public function setDatePreset(string $preset): void
    {
        if ($preset === 'yesterday') {
            $day = now()->subDay();
            $this->dateFrom = $day->toDateString();
            $this->dateTo = $day->toDateString();

            return;
        }

        if ($preset === 'last7') {
            $this->dateFrom = now()->subDays(6)->toDateString();
            $this->dateTo = now()->toDateString();

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

        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $capability = \App\Support\Rbac\StaffCapability::for($user);

        if ($capability->isTenantAdmin()) {
            return true;
        }

        return $capability->canCollect()
            || $capability->canPayments()
            || $capability->canBilling()
            || BillCollectionDesk::canAccess();
    }

    /** @return array<string, mixed> */
    public function getReport(): array
    {
        $user = auth()->user();
        $capability = $user ? \App\Support\Rbac\StaffCapability::for($user) : null;
        $includeVendor = $this->includeCompanyExpenses
            && $capability !== null
            && $capability->canAccounting();

        return app(BillingFundFlowService::class)->report(
            Carbon::parse($this->dateFrom ?: now()->toDateString()),
            Carbon::parse($this->dateTo ?: now()->toDateString()),
            $this->collectorId ?: null,
            $this->search ?: null,
            null,
            $includeVendor,
        );
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    public function getCollectorOptions(): array
    {
        return app(BillingFundFlowService::class)
            ->collectorsForFilter()
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name])
            ->values()
            ->all();
    }

    public function canSeeCompanyExpenses(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        return \App\Support\Rbac\StaffCapability::for($user)->canAccounting();
    }
}
