<?php

namespace App\Console\Commands;

use App\Models\Reseller;
use App\Services\Resellers\ResellerBulkDueReminderService;
use Illuminate\Console\Command;

class SendResellerDueRemindersCommand extends Command
{
    protected $signature = 'isp:send-reseller-due-reminders
                            {--tenant= : Limit to tenant id}
                            {--reseller= : Limit to reseller id or code}
                            {--days-overdue= : Only bills due at least N days ago (default from config)}
                            {--dry-run : Count without sending}';

    protected $description = 'Send SMS/email/Telegram due reminders for reseller subscribers with open bills';

    public function handle(ResellerBulkDueReminderService $bulk): int
    {
        if (! config('reseller_billing.due_reminders.bulk_enabled', true)) {
            $this->warn('Reseller bulk due reminders are disabled (reseller_billing.due_reminders.bulk_enabled).');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $daysOpt = $this->option('days-overdue');
        $minDays = $daysOpt !== null && $daysOpt !== ''
            ? (int) $daysOpt
            : (int) config('reseller_billing.due_reminders.bulk_min_days_overdue', 0);

        $resellerOpt = $this->option('reseller');
        if ($resellerOpt !== null && $resellerOpt !== '') {
            $reseller = Reseller::query()
                ->withoutGlobalScopes()
                ->when($this->option('tenant'), fn ($q) => $q->where('tenant_id', (int) $this->option('tenant')))
                ->where(fn ($q) => $q->where('id', (int) $resellerOpt)->orWhere('code', (string) $resellerOpt))
                ->first();

            if ($reseller === null) {
                $this->error('Reseller not found.');

                return self::FAILURE;
            }

            $result = $bulk->runForReseller($reseller, $dryRun, $minDays);
            $this->info(sprintf(
                '%sPartner %s — invoices: %d, sent: %d, skipped: %d',
                $dryRun ? '[dry-run] ' : '',
                $reseller->code,
                $result['invoices'],
                $result['sent'],
                $result['skipped'],
            ));

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant') !== null ? (int) $this->option('tenant') : null;
        $result = $bulk->runAll($tenantId, $dryRun, $minDays);

        $this->info(sprintf(
            '%sPartners: %d, invoices scanned: %d, sent: %d, skipped: %d',
            $dryRun ? '[dry-run] ' : '',
            $result['resellers'],
            $result['invoices'],
            $result['sent'],
            $result['skipped'],
        ));

        return self::SUCCESS;
    }
}
