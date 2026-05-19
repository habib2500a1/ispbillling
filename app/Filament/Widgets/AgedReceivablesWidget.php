<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AgedReceivablesWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Aged receivables';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    protected function getStats(): array
    {
        $today = now()->toDateString();
        $d30 = now()->subDays(30)->toDateString();
        $d60 = now()->subDays(60)->toDateString();
        $d90 = now()->subDays(90)->toDateString();

        $base = Invoice::query()
            ->whereIn('status', ['open', 'partial', 'draft'])
            ->whereRaw('(total - amount_paid) > 0');

        $b0 = (clone $base)->whereDate('due_date', '>=', $today)->sum(DB::raw('total - amount_paid'));
        $b30 = (clone $base)->whereDate('due_date', '<', $today)->whereDate('due_date', '>=', $d30)->sum(DB::raw('total - amount_paid'));
        $b60 = (clone $base)->whereDate('due_date', '<', $d30)->whereDate('due_date', '>=', $d60)->sum(DB::raw('total - amount_paid'));
        $b90 = (clone $base)->whereDate('due_date', '<', $d60)->sum(DB::raw('total - amount_paid'));

        return [
            Stat::make('Due / not yet overdue', $this->money($b0))
                ->description('Due date ≥ today'),
            Stat::make('Aging 1–30 days', $this->money($b30)),
            Stat::make('Aging 31–60 days', $this->money($b60)),
            Stat::make('Aging 60+ days', $this->money($b90)),
        ];
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', ',').' BDT';
    }
}
