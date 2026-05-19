<?php

namespace App\Filament\Resources\OltResource\Widgets;

use App\Services\Optical\OltOnuOpticalSummaryService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class OltOnuOpticalStatsWidget extends BaseWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $page = $this->getPage();
        $olt = method_exists($page, 'getRecord') ? $page->getRecord() : null;
        if ($olt === null) {
            return [
                Stat::make('ONUs on this OLT', '—')->description('Loading…'),
            ];
        }

        $s = app(OltOnuOpticalSummaryService::class)->forOlt($olt);

        return [
            Stat::make('ONUs on this OLT', (string) $s['total'])
                ->description($s['with_rx'].' with RX reading')
                ->icon('heroicon-o-cpu-chip'),
            Stat::make('Avg RX', $s['avg_rx'] !== null ? $s['avg_rx'].' dBm' : '—')
                ->description($s['min_rx'] !== null ? "Min {$s['min_rx']} · Max {$s['max_rx']} dBm" : 'No optical data yet')
                ->color($s['avg_rx'] !== null && $s['avg_rx'] >= -15 ? 'success' : 'warning'),
            Stat::make('Good signal', (string) ($s['excellent'] + $s['good']))
                ->description("Excellent {$s['excellent']} · Good {$s['good']}")
                ->color('success'),
            Stat::make('Weak / critical', (string) ($s['warning'] + $s['critical']))
                ->description("Weak {$s['warning']} · Critical {$s['critical']} · Offline {$s['offline']}")
                ->color($s['critical'] > 0 ? 'danger' : ($s['warning'] > 0 ? 'warning' : 'success')),
            Stat::make('No dBm data', (string) $s['no_data'])
                ->description('Sync optical or push webhook')
                ->color($s['no_data'] > 0 ? 'gray' : 'success'),
        ];
    }
}
