<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class MonthlyReportStats extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    protected function getStats(): array
    {
        $data = Cache::remember('widget:monthly-report:'.now()->format('Y-m'), now()->addMinutes(5), function (): array {
            $start = now()->startOfMonth();
            $end = now()->endOfMonth();

            $invoiced = (float) Invoice::query()
                ->whereBetween('issue_date', [$start->toDateString(), $end->toDateString()])
                ->sum('total');

            $collected = (float) Payment::query()
                ->where('status', 'completed')
                ->whereNotNull('paid_at')
                ->whereBetween('paid_at', [$start, $end])
                ->sum('amount');

            $arDue = (float) Invoice::query()
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->whereRaw('(total - amount_paid) > 0')
                ->sum(DB::raw('total - amount_paid'));

            return ['invoiced' => $invoiced, 'collected' => $collected, 'arDue' => $arDue];
        });

        return [
            Stat::make('Invoiced (this month)', $this->formatMoney($data['invoiced']).' BDT')
                ->description('Sum of invoice totals by issue date'),
            Stat::make('Collected (this month)', $this->formatMoney($data['collected']).' BDT')
                ->description('Completed payments by paid date'),
            Stat::make('Outstanding AR', $this->formatMoney($data['arDue']).' BDT')
                ->description('Open / partial / draft with balance due'),
        ];
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', ',');
    }
}
