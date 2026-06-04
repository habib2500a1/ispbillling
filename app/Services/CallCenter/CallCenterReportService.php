<?php

namespace App\Services\CallCenter;

use App\Models\CallLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class CallCenterReportService
{
    /**
     * @return list<array{
     *     staff_user_id: int|null,
     *     staff_name: string,
     *     total: int,
     *     inbound: int,
     *     outbound: int,
     *     answered: int,
     *     missed: int,
     *     avg_duration_seconds: int
     * }>
     */
    public function staffSummary(?Carbon $from = null, ?Carbon $to = null): array
    {
        $from ??= now()->startOfMonth();
        $to ??= now()->endOfDay();

        $answeredList = implode("','", ['answered', 'completed']);
        $missedList = implode("','", ['missed', 'no_answer', 'busy', 'failed']);

        $rows = CallLog::query()
            ->whereBetween('started_at', [$from, $to])
            ->select([
                'staff_user_id',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN direction = 'inbound' THEN 1 ELSE 0 END) as inbound"),
                DB::raw("SUM(CASE WHEN direction = 'outbound' THEN 1 ELSE 0 END) as outbound"),
                DB::raw("SUM(CASE WHEN status IN ('{$answeredList}') THEN 1 ELSE 0 END) as answered"),
                DB::raw("SUM(CASE WHEN status IN ('{$missedList}') THEN 1 ELSE 0 END) as missed"),
                DB::raw('COALESCE(AVG(NULLIF(duration_seconds, 0)), 0) as avg_duration_seconds'),
            ])
            ->groupBy('staff_user_id')
            ->orderByDesc('total')
            ->get();

        $userNames = User::query()
            ->whereIn('id', $rows->pluck('staff_user_id')->filter()->unique())
            ->pluck('name', 'id');

        return $rows->map(function ($row) use ($userNames): array {
            $staffId = $row->staff_user_id !== null ? (int) $row->staff_user_id : null;

            return [
                'staff_user_id' => $staffId,
                'staff_name' => $staffId !== null
                    ? (string) ($userNames[$staffId] ?? 'Staff #'.$staffId)
                    : 'Unassigned',
                'total' => (int) $row->total,
                'inbound' => (int) $row->inbound,
                'outbound' => (int) $row->outbound,
                'answered' => (int) $row->answered,
                'missed' => (int) $row->missed,
                'avg_duration_seconds' => (int) round((float) $row->avg_duration_seconds),
            ];
        })->all();
    }

    /**
     * @return array{total_calls: int, answered: int, missed: int, outbound: int, inbound: int}
     */
    public function totals(?Carbon $from = null, ?Carbon $to = null): array
    {
        $rows = $this->staffSummary($from, $to);

        return [
            'total_calls' => array_sum(array_column($rows, 'total')),
            'answered' => array_sum(array_column($rows, 'answered')),
            'missed' => array_sum(array_column($rows, 'missed')),
            'outbound' => array_sum(array_column($rows, 'outbound')),
            'inbound' => array_sum(array_column($rows, 'inbound')),
        ];
    }
}
