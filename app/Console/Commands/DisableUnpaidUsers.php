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
        app(ScheduledTasksController::class)->userDisable();
        $this->info('Unpaid customer disable sweep completed.');

        return self::SUCCESS;
    }
}
