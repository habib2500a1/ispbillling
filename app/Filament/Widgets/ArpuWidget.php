<?php

namespace App\Filament\Widgets;

use App\Models\Customer;
use App\Models\Payment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ArpuWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = [
        'default' => 'full',
        'lg' => 2,
    ];

    protected ?string $heading = 'Revenue per user';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }

    protected function getStats(): array
    {
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();

        $collected = (float) Payment::query()
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$start, $end])
            ->sum('amount');

        $active = Customer::query()->where('status', 'active')->count();
        $arpu = $active > 0 ? round($collected / $active, 2) : 0.0;

        $byMethod = Payment::query()
            ->select('method', DB::raw('SUM(amount) as total'))
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$start, $end])
            ->groupBy('method')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ($r->method ?? 'unknown').': '.number_format((float) $r->total, 0))
            ->implode(' · ');

        return [
            Stat::make('ARPU (this month)', number_format($arpu, 2, '.', ',').' BDT')
                ->description('Collected / active customers (proxy)'),
            Stat::make('Top payment methods', $byMethod !== '' ? $byMethod : '—')
                ->description('Completed payments this month'),
        ];
    }
}
