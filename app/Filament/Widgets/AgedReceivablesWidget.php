<?php

namespace App\Filament\Widgets;

use App\Models\Invoice;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
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
        $buckets = Cache::remember('widget:aged-receivables:'.now()->toDateString(), now()->addMinutes(5), function (): array {
            $today = now()->toDateString();
            $d30 = now()->subDays(30)->toDateString();
            $d60 = now()->subDays(60)->toDateString();

            // Single query: split the outstanding balance into aging buckets via CASE.
            $row = Invoice::query()
                ->whereIn('status', ['open', 'partial', 'draft'])
                ->whereRaw('(total - amount_paid) > 0')
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN due_date >= ? THEN total - amount_paid ELSE 0 END), 0) AS b0,
                    COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total - amount_paid ELSE 0 END), 0) AS b30,
                    COALESCE(SUM(CASE WHEN due_date < ? AND due_date >= ? THEN total - amount_paid ELSE 0 END), 0) AS b60,
                    COALESCE(SUM(CASE WHEN due_date < ? THEN total - amount_paid ELSE 0 END), 0) AS b90
                ', [$today, $today, $d30, $d30, $d60, $d60])
                ->first();

            return [
                'b0' => (float) ($row->b0 ?? 0),
                'b30' => (float) ($row->b30 ?? 0),
                'b60' => (float) ($row->b60 ?? 0),
                'b90' => (float) ($row->b90 ?? 0),
            ];
        });

        return [
            Stat::make('Due / not yet overdue', $this->money($buckets['b0']))
                ->description('Due date ≥ today'),
            Stat::make('Aging 1–30 days', $this->money($buckets['b30'])),
            Stat::make('Aging 31–60 days', $this->money($buckets['b60'])),
            Stat::make('Aging 60+ days', $this->money($buckets['b90'])),
        ];
    }

    private function money(float $v): string
    {
        return number_format($v, 2, '.', ',').' BDT';
    }
}
