<?php

namespace App\Console\Commands;

use App\Jobs\RunDunningRemindersJob;
use App\Services\Billing\DunningLadderService;
use App\Support\QueueJobDispatcher;
use Illuminate\Console\Command;

class SendInvoiceDueRemindersCommand extends Command
{
    protected $signature = 'isp:send-invoice-due-reminders
                            {--dry-run : Log only, do not send}
                            {--queued : Internal: skip re-queue}';

    protected $description = 'Smart dunning: due soon, due today, and overdue reminders (SMS/email).';

    public function handle(DunningLadderService $dunning): int
    {
        $anyEnabled = (bool) config('billing.dunning.enabled', true)
            && (
                (bool) config('notifications.events.invoice_due.enabled', false)
                || (bool) config('notifications.events.invoice_due_soon.enabled', false)
                || (bool) config('SMS_REMINDERS_ENABLED', false)
            );

        if (! $anyEnabled && ! config('notifications.events.invoice_due_soon.enabled', false)) {
            $this->info('Dunning reminders disabled. Enable SMS_REMINDERS_ENABLED or notification events.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');

        if (config('queue_ops.heavy_jobs_enabled', false) && ! $dryRun && ! $this->option('queued')) {
            QueueJobDispatcher::run(new RunDunningRemindersJob(false), fn () => $dunning->run(false));
            $this->info('Dunning reminders queued.');

            return self::SUCCESS;
        }

        $result = $dunning->run($dryRun);

        $this->info(sprintf(
            '%sSent %d reminder(s), skipped %d.',
            $this->option('dry-run') ? '[dry-run] ' : '',
            $result['sent'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
