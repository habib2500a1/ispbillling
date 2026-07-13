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
        if ((int) now()->format('j') !== 1) {
            $this->info('Skipped — not 1st of month.');

            return self::SUCCESS;
        }

        app(ScheduledTasksController::class)->allCustomersMonthlyBillSMS();
        $this->info('Monthly bill SMS sent.');

        return self::SUCCESS;
    }
}
