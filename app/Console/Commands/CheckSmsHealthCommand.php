<?php

namespace App\Console\Commands;

use App\Models\NotificationLog;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Console\Command;

class CheckSmsHealthCommand extends Command
{
    protected $signature = 'isp:check-sms-health';

    protected $description = 'Alert ops if SMS failure rate is high (last 24h).';

    public function handle(): int
    {
        if (! config('notifications.sms.enabled', false)) {
            $this->info('SMS disabled.');

            return self::SUCCESS;
        }

        $since = now()->subHours((int) config('alerts.sms_failure_check_hours', 24));
        $total = NotificationLog::withoutGlobalScopes()
            ->where('channel', 'sms')
            ->where('created_at', '>=', $since)
            ->count();

        if ($total < 10) {
            $this->info('Not enough SMS volume to evaluate.');

            return self::SUCCESS;
        }

        $failed = NotificationLog::withoutGlobalScopes()
            ->where('channel', 'sms')
            ->where('created_at', '>=', $since)
            ->where('status', 'failed')
            ->count();

        $rate = $failed / max(1, $total);
        $threshold = (float) config('alerts.sms_failure_rate_threshold', 0.25);

        $this->line("SMS failure rate: ".round($rate * 100, 1)."% ({$failed}/{$total})");

        if ($rate >= $threshold) {
            app(NotificationDispatcher::class)->notifyOps(
                1,
                NotificationEvent::OUTAGE,
                ['message' => 'High SMS failure rate: '.round($rate * 100, 1)."% ({$failed}/{$total})", 'count' => $failed],
            );
            $this->warn('Ops alert sent.');
        }

        return self::SUCCESS;
    }
}
