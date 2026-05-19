<?php

namespace App\Filament\Widgets\Concerns;

use Illuminate\Support\Facades\Log;

trait SafeBandwidthChartData
{
    /**
     * @param  callable(): array{labels: list<string>, datasets: list<array<string, mixed>>}  $builder
     * @return array{labels: list<string>, datasets: list<array<string, mixed>>}
     */
    protected function safeChartData(callable $builder): array
    {
        try {
            return $builder();
        } catch (\Throwable $e) {
            Log::warning('bandwidth.chart_render_failed', [
                'widget' => static::class,
                'error' => $e->getMessage(),
            ]);

            return [
                'labels' => [now()->format('H:i:s')],
                'datasets' => [
                    [
                        'label' => 'No data',
                        'data' => [0],
                        'borderColor' => '#9ca3af',
                        'fill' => false,
                    ],
                ],
            ];
        }
    }
}
