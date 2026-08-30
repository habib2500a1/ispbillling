<?php

namespace App\Console\Commands;

use App\Http\Controllers\ScheduledTasksController;
use Illuminate\Console\Command;

class SendMonthlyBillSms extends Command
{
    protected $signature = 'cpagol:send-monthly-bill-sms';

    protected $description = 'Send monthly bill SMS (1st of month only)';

    public function handle(): int
    {
        $day = max(1, min(28, (int) (siteUrlSettings('monthly_bill_sms_day') ?: 1)));
        if ((int) now()->format('j') !== $day) {
            $this->info("Skipped — SMS day is {$day}, today is ".now()->format('j').'.');

            return self::SUCCESS;
        }

        app(ScheduledTasksController::class)->allCustomersMonthlyBillSMS();
        $this->info('Monthly bill SMS sent.');

        return self::SUCCESS;
    }
}
