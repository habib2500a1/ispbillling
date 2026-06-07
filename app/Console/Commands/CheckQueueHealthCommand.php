<?php

namespace App\Console\Commands;

use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;
use App\Support\NotificationEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

class CheckQueueHealthCommand extends Command
{
    protected $signature = 'isp:check-queue-health';

    protected $description = 'Alert ops when failed queue jobs exceed the configured threshold.';

    public function handle(): int
    {
        if (! config('alerts.queue_health_enabled', true)) {
            $this->info('Queue health alerts disabled.');

            return self::SUCCESS;
        }

        if (! Schema::hasTable('failed_jobs')) {
            $this->info('No failed_jobs table.');

            return self::SUCCESS;
        }

        $minutes = max(1, (int) config('alerts.queue_failed_check_minutes', 15));
        $threshold = max(1, (int) config('alerts.queue_failed_jobs_threshold', 5));
        $since = now()->subMinutes($minutes);

        $failed = (int) DB::table('failed_jobs')
            ->where('failed_at', '>=', $since)
            ->count();

        $this->line("Failed jobs (last {$minutes}m): {$failed} (threshold {$threshold})");

        if ($failed < $threshold) {
            return self::SUCCESS;
        }

        $cacheKey = 'isp:queue-health-alert:'.now()->format('Y-m-d-H');
        if (Cache::has($cacheKey)) {
            $this->info('Alert already sent this hour.');

            return self::SUCCESS;
        }

        $message = "Queue alert: {$failed} failed jobs in the last {$minutes} minutes (threshold {$threshold}). Check Horizon → Failed Jobs.";
        $tenantId = 1;

        app(NotificationDispatcher::class)->notifyOps(
            $tenantId,
            NotificationEvent::OUTAGE,
            ['message' => $message, 'count' => $failed],
        );

        $email = trim((string) config('alerts.ops_email', ''));
        if ($email !== '' && config('notifications.email.enabled', true)) {
            Mail::raw($message, function ($mail) use ($email): void {
                $mail->to($email)->subject('ISP Platform — queue failure alert');
            });
            $this->line("Email sent to {$email}");
        }

        $phone = trim((string) config('alerts.ops_sms_phone', ''));
        if ($phone !== '' && config('notifications.sms.enabled', false)) {
            app(NotificationDispatcher::class)->send(
                $tenantId,
                null,
                NotificationEvent::OUTAGE,
                NotificationChannel::SMS,
                $phone,
                $message,
                ['bypass_event_gate' => true],
            );
            $this->line("SMS sent to {$phone}");
        }

        Cache::put($cacheKey, true, now()->addHour());
        $this->warn('Ops alert sent.');

        return self::SUCCESS;
    }
}
