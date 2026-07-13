<?php

namespace App\Console\Commands;

use App\Http\Controllers\ScheduledTasksController;
use Illuminate\Console\Command;

class PaymentReminderAlerts extends Command
{
    protected $signature = 'cpagol:payment-reminder-alerts';

    protected $description = 'Send payment reminder alerts';

    public function handle(): int
    {
        app(ScheduledTasksController::class)->createAlert();
        $this->info('Payment reminder alerts processed.');

        return self::SUCCESS;
    }
}
