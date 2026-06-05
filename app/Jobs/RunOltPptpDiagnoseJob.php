<?php

namespace App\Jobs;

use App\Models\Device;
use App\Services\Olt\OltPptpTunnelService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class RunOltPptpDiagnoseJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 120;

    public int $tries = 1;

    public function __construct(
        public int $oltId,
    ) {}

    public function handle(OltPptpTunnelService $pptp): void
    {
        $key = self::cacheKey($this->oltId);
        Cache::put($key, ['status' => 'running', 'started_at' => now()->toIso8601String()], 600);

        $olt = Device::withoutGlobalScopes()->olts()->find($this->oltId);
        if ($olt === null) {
            Cache::put($key, [
                'status' => 'error',
                'summary' => 'OLT not found.',
                'lines' => [],
                'success' => false,
            ], 600);

            return;
        }

        $compare = $pptp->compareAllReachMethods($olt->fresh());
        $lines = [
            'Direct ping: '.($compare['direct']['reachable'] ? 'OK' : 'FAIL'),
        ];
        foreach ($compare['methods'] as $m) {
            $status = ! ($m['tried'] ?? false)
                ? 'SKIP'
                : (($m['success'] ?? false) ? 'OK' : 'FAIL');
            $lines[] = sprintf('%s [%s]: %s', $m['label'] ?? $m['method'], $status, $m['message'] ?? '');
        }
        $lines[] = 'Recommended: '.$compare['recommended'];

        $winner = (string) ($compare['recommended'] ?? 'none');
        $success = $winner !== 'none';

        Cache::put($key, [
            'status' => 'done',
            'success' => $success,
            'summary' => (string) ($compare['summary_bn'] ?? ''),
            'lines' => $lines,
            'compare' => $compare,
            'finished_at' => now()->toIso8601String(),
        ], 600);
    }

    public function failed(?\Throwable $exception): void
    {
        Cache::put(self::cacheKey($this->oltId), [
            'status' => 'error',
            'success' => false,
            'summary' => 'VPN test failed — '.$exception?->getMessage(),
            'lines' => [],
            'finished_at' => now()->toIso8601String(),
        ], 600);
    }

    public static function cacheKey(int $oltId): string
    {
        return 'olt_pptp_diag:'.$oltId;
    }

    /**
     * Run compare immediately (panel — does not need queue worker).
     *
     * @return array<string, mixed>|null
     */
    public static function runNow(int $oltId): ?array
    {
        $job = new self($oltId);
        $job->handle(app(OltPptpTunnelService::class));

        $cached = Cache::get(self::cacheKey($oltId));

        return is_array($cached) ? $cached : null;
    }
}
