<?php

namespace App\Console\Commands;

use App\Http\Controllers\ScheduledTasksController;
use Illuminate\Console\Command;

class DisableUnpaidUsers extends Command
{
    protected $signature = 'cpagol:disable-unpaid-users';

    protected $description = 'Disable unpaid customers';

    public function handle(): int
    {
        $tz = config('app.timezone') ?: 'Asia/Dhaka';
        if (now($tz)->isLastOfMonth() && ! \App\Services\Billing\MonthlyBillSchedule::eomInactiveAllowed()) {
            $this->info('Skipping disable — last-day inactive process is off.');

            return self::SUCCESS;
        }

        app(ScheduledTasksController::class)->userDisable();
        $this->info('Unpaid customer disable sweep completed.');

        return self::SUCCESS;
    }
}
