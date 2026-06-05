<?php

namespace App\Filament\Pages;

use App\Models\CollectorCollection;
use App\Models\User;
use App\Services\Collector\CollectionBalanceStatementPdfService;
use App\Services\Collector\CollectorWalletService;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CollectionBalanceStatement extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string $view = 'filament.pages.collection-balance-statement';

    protected static ?string $navigationLabel = 'Collection Balance Statement';

    protected static ?string $title = 'Collection Balance Statement';

    protected static ?string $navigationGroup = 'Payments';

    protected static ?int $navigationSort = 4;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $groupBy = 'staff';

    public static function canAccess(): bool
    {
        $user = auth()->user();
        if ($user === null) {
            return false;
        }

        $capability = \App\Support\Rbac\StaffCapability::for($user);

        return $capability->isTenantAdmin()
            || $user->can('collections.view')
            || $user->can('payments.view')
            || $user->can('collections.approve');
    }

    public function mount(): void
    {
        $preset = request()->string('preset')->toString();
        if ($preset === 'month') {
            $this->setDatePreset('month');
        } elseif ($preset === 'week') {
            $this->setDatePreset('week');
        } elseif ($preset === 'yesterday') {
            $this->setDatePreset('yesterday');
        } else {
            $this->setDatePreset('today');
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

        if ($this->dateFrom === now()->startOfWeek()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'week';
        }

        if ($this->dateFrom === now()->startOfMonth()->toDateString() && $this->dateTo === now()->toDateString()) {
            return 'month';
        }

        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export_pdf')
                ->label('Export PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->action(fn () => $this->exportPdf()),
            Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->exportCsv()),
        ];
    }

    public function exportPdf(): \Illuminate\Http\Response
    {
        return app(CollectionBalanceStatementPdfService::class)->download(
            $this->getStatement(),
            $this->dateFrom ?: now()->toDateString(),
            $this->dateTo ?: now()->toDateString(),
        );
    }

    /**
     * @return array{summary: array{total_collected: float, total_transactions: int, total_balance_due: float, staff_count: int}, staff: list<array{id: int, name: string, collected_amount: float, transaction_count: int, balance_due: float, last_collection: ?string}>}
     */
    public function getStatement(): array
    {
        $tenantId = TenantResolver::requiredTenantId();
        $dateFrom = Carbon::parse($this->dateFrom ?: now()->toDateString())->startOfDay();
        $dateTo = Carbon::parse($this->dateTo ?: now()->toDateString())->endOfDay();

        $collections = CollectorCollection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('collected_at', [$dateFrom, $dateTo])
            ->selectRaw('collector_id, SUM(amount) as total_collected, COUNT(*) as transaction_count, MAX(collected_at) as last_collection')
            ->groupBy('collector_id')
            ->orderByDesc('total_collected')
            ->get();

        $collectorIds = $collections->pluck('collector_id')->unique()->all();
        $users = User::query()->whereIn('id', $collectorIds)->get()->keyBy('id');

        $walletService = app(CollectorWalletService::class);
        $staff = [];

        foreach ($collections as $collection) {
            $collectorId = (int) $collection->collector_id;
            $user = $users->get($collectorId);
            if ($user === null) {
                continue;
            }

            $wallet = $walletService->wallet($collectorId);

            $staff[] = [
                'id' => $collectorId,
                'name' => $user->name,
                'collected_amount' => round((float) $collection->total_collected, 2),
                'transaction_count' => (int) $collection->transaction_count,
                'balance_due' => round((float) $wallet['cash_in_hand'], 2),
                'last_collection' => $collection->last_collection 
                    ? Carbon::parse($collection->last_collection)->format('Y-m-d H:i') 
                    : null,
            ];
        }

        $summary = [
            'total_collected' => round(array_sum(array_column($staff, 'collected_amount')), 2),
            'total_transactions' => array_sum(array_column($staff, 'transaction_count')),
            'total_balance_due' => round(array_sum(array_column($staff, 'balance_due')), 2),
            'staff_count' => count($staff),
        ];

        return [
            'summary' => $summary,
            'staff' => $staff,
        ];
    }

    public function exportCsv(): StreamedResponse
    {
        $statement = $this->getStatement();

        $filename = sprintf(
            'collection-balance-statement_%s_%s.csv',
            $this->dateFrom,
            $this->dateTo
        );

        return response()->streamDownload(function () use ($statement): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, ['Collection Balance Statement']);
            fputcsv($out, ['Date Range:', $this->dateFrom.' to '.$this->dateTo]);
            fputcsv($out, ['Generated:', now()->format('Y-m-d H:i:s')]);
            fputcsv($out, []);

            fputcsv($out, ['Summary']);
            fputcsv($out, ['Total Collected', number_format($statement['summary']['total_collected'], 2).' BDT']);
            fputcsv($out, ['Total Transactions', $statement['summary']['total_transactions']]);
            fputcsv($out, ['Total Balance Due', number_format($statement['summary']['total_balance_due'], 2).' BDT']);
            fputcsv($out, ['Staff Count', $statement['summary']['staff_count']]);
            fputcsv($out, []);

            fputcsv($out, ['Staff Name', 'Collected Amount (BDT)', 'Transaction Count', 'Balance Due (BDT)', 'Last Collection']);

            foreach ($statement['staff'] as $row) {
                fputcsv($out, [
                    $row['name'],
                    number_format($row['collected_amount'], 2),
                    $row['transaction_count'],
                    number_format($row['balance_due'], 2),
                    $row['last_collection'] ?? '—',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }
}
