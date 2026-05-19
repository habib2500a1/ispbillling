<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingOpsMetricsService;
use Filament\Pages\Page;

class BillingOverview extends Page
{
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.billing-overview';

    protected static ?string $navigationLabel = 'Billing center';

    protected static ?string $title = 'Billing center';

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return auth()->check();
    }

    /**
     * @return array<string, int|float|string>
     */
    public function getStats(): array
    {
        $openBase = Invoice::query()->whereNotIn('status', ['paid', 'void', 'cancelled', 'draft']);

        $overdue = (clone $openBase)
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereRaw('(total - amount_paid) > 0')
            ->count();

        $outstanding = (clone $openBase)
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')
            ->value('due');

        $collectedMonth = (float) Payment::query()
            ->where('status', 'completed')
            ->whereNotNull('paid_at')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $ops = app(BillingOpsMetricsService::class)->snapshot();

        return [
            'open' => (clone $openBase)->count(),
            'overdue' => $overdue,
            'draft' => Invoice::query()->where('status', 'draft')->count(),
            'collected_month' => $collectedMonth,
            'outstanding' => max(0.0, (float) $outstanding),
            'ops' => $ops,
        ];
    }
}
