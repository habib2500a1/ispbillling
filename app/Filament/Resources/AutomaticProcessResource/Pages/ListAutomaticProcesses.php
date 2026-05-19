<?php

namespace App\Filament\Resources\AutomaticProcessResource\Pages;

use App\Filament\Resources\AutomaticProcessResource;
use App\Filament\Widgets\AutomaticProcessHealthWidget;
use App\Models\AutomaticProcess;
use App\Models\AutomaticProcessRun;
use App\Services\Automation\SchedulerStatus;
use App\Support\IspTimezone;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\View\View;

class ListAutomaticProcesses extends ListRecords
{
    protected static string $resource = AutomaticProcessResource::class;

    protected static ?string $title = 'Automatic process';

    public function getSubheading(): ?string
    {
        return 'Schedules use '.IspTimezone::description().' (change in System → Company setup). Cron: isp:run-automatic-processes/min. Now: '.IspTimezone::nowFormatted('g:i A');
    }

    protected function getHeaderWidgets(): array
    {
        return [
            AutomaticProcessHealthWidget::class,
        ];
    }

    /**
     * @return array{enabled: int, failed_24h: int, next_hour: int}
     */
    public function getStats(): array
    {
        $base = AutomaticProcess::query();

        return [
            'enabled' => (clone $base)->where('enabled', true)->count(),
            'failed_24h' => AutomaticProcessRun::query()
                ->where('status', 'failed')
                ->where('started_at', '>=', now()->subDay())
                ->count(),
            'next_hour' => (clone $base)
                ->where('enabled', true)
                ->where('next_run_at', '<=', now()->addHour())
                ->count(),
        ];
    }

    public function getFooter(): ?View
    {
        return view('filament.automatic-process-footer', [
            'stats' => $this->getStats(),
            'cron' => app(SchedulerStatus::class)->cronHealth(),
        ]);
    }
}
