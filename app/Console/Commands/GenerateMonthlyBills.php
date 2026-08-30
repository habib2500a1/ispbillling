<?php

namespace App\Console\Commands;

use App\Http\Controllers\ScheduledTasksController;
use App\Services\Billing\MonthlyBillSchedule;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyBills extends Command
{
    protected $signature = 'cpagol:generate-monthly-bills
                            {--force : Generate for all customers (ignore billing_day / last-of-month gate)}
                            {--date= : Reference date Y-m-d (default today)}';

    protected $description = 'Generate monthly bills for today\'s billing_day customers (or all on last day / --force)';

    public function handle(): int
    {
        $tz = config('app.timezone') ?: 'Asia/Dhaka';
        $date = $this->option('date')
            ? Carbon::parse($this->option('date'), $tz)->startOfDay()
            : Carbon::now($tz)->startOfDay();

        $force = (bool) $this->option('force');
        $billingDay = (int) $date->day;
        $isLastOfMonth = $date->isLastOfMonth();

        if ($force) {
            $this->info('Force mode — generating bills for all customers…');
            app(ScheduledTasksController::class)->createMonthlyBill(null, true);
            $this->info('Monthly bills generated (force).');

            return self::SUCCESS;
        }

        if (MonthlyBillSchedule::mode() === 'global') {
            if (! MonthlyBillSchedule::shouldGenerateAllOn($date)) {
                $this->info('Skipping — Super Admin bill day is '.MonthlyBillSchedule::day().', today is '.$billingDay.'.');

                return self::SUCCESS;
            }

            $this->info('Global bill day '.MonthlyBillSchedule::day().' — generating bills for all customers…');
            app(ScheduledTasksController::class)->createMonthlyBill(null, true);
            $this->info('Monthly bills generated.');

            return self::SUCCESS;
        }

        // Daily: customers whose billing_day matches today.
        // Also run full sweep on last calendar day for anyone still on legacy null billing_day.
        $this->info("Generating bills for billing_day={$billingDay}".($isLastOfMonth ? ' (+ last-of-month sweep)' : '').'…');
        app(ScheduledTasksController::class)->createMonthlyBill($billingDay, false);

        if ($isLastOfMonth) {
            // Catch any remaining without a billing_day that were not covered.
            app(ScheduledTasksController::class)->createMonthlyBill(null, true);
            $this->info('Last-of-month full sweep complete.');
        }

        $this->info('Monthly bills generated.');

        return self::SUCCESS;
    }
}
