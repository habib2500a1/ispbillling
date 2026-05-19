<?php

namespace App\Services\Automation;

use App\Models\AutomaticProcessRun;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AutomaticProcessRunCsvExporter
{
    public function download(?int $processId = null, int $days = 30): StreamedResponse
    {
        $since = now()->subDays(max(1, $days));

        $query = AutomaticProcessRun::query()
            ->with('process:id,name,slug,artisan_command')
            ->where('started_at', '>=', $since)
            ->orderByDesc('id');

        if ($processId !== null) {
            $query->where('automatic_process_id', $processId);
        }

        $filename = $processId
            ? 'automatic-process-runs-'.$processId.'-'.now()->format('Y-m-d').'.csv'
            : 'automatic-process-runs-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'id',
                'process',
                'slug',
                'command',
                'triggered_by',
                'status',
                'exit_code',
                'started_at',
                'finished_at',
                'duration_seconds',
                'output',
            ]);

            $query->chunkById(200, function ($runs) use ($out): void {
                foreach ($runs as $run) {
                    /** @var AutomaticProcessRun $run */
                    fputcsv($out, [
                        $run->id,
                        $run->process?->name ?? '',
                        $run->process?->slug ?? '',
                        $run->process?->artisan_command ?? '',
                        $run->triggered_by,
                        $run->status,
                        $run->exit_code,
                        $run->started_at?->toDateTimeString(),
                        $run->finished_at?->toDateTimeString(),
                        $run->durationSeconds(),
                        str_replace(["\r\n", "\n"], ' ', (string) $run->output),
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
