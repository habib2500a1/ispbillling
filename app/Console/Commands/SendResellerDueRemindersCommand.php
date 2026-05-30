<?php

namespace App\Console\Commands;

use App\Services\Resellers\ResellerDueReminderService;
use Illuminate\Console\Command;

class SendResellerDueRemindersCommand extends Command
{
    protected $signature = 'isp:send-reseller-due-reminders
                            {--dry-run : Log counts only, do not create notifications}
                            {--tenant= : Limit to tenant id}';

    protected $description = 'Notify resellers about due subscribers and upcoming service expiry (portal notifications).';

    public function handle(ResellerDueReminderService $reminders): int
    {
        if (! config('automation.reseller_due_reminders.enabled', true)) {
            $this->info('Reseller due reminders disabled (automation.reseller_due_reminders.enabled).');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $reminders->run($dryRun, $tenantId);

        $this->info(sprintf(
            '%sReseller due reminders: %d sent, %d skipped (%d reseller(s) with due/expiring data).',
            $dryRun ? '[dry-run] ' : '',
            $result['sent'],
            $result['skipped'],
            $result['resellers'],
        ));

        return self::SUCCESS;
    }
}
