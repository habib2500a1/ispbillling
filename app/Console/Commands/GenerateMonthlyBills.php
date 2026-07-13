<?php

namespace App\Console\Commands;

use App\Http\Controllers\ScheduledTasksController;
use Illuminate\Console\Command;

class GenerateMonthlyBills extends Command
{
    protected $signature = 'cpagol:generate-monthly-bills';

    protected $description = 'Generate monthly bills (last day of month only)';

    public function handle(): int
    {
        if (! now()->isLastOfMonth()) {
            $this->info('Skipped — not last day of month.');

            return self::SUCCESS;
        }

        app(ScheduledTasksController::class)->createMonthlyBill();
        $this->info('Monthly bills generated.');

        return self::SUCCESS;
    }
}
