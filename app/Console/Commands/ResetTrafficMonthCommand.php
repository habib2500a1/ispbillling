<?php

namespace App\Console\Commands;

use App\Services\Bandwidth\CustomerTrafficUsageService;
use Illuminate\Console\Command;

class ResetTrafficMonthCommand extends Command
{
    protected $signature = 'app:reset-traffic-month';

    protected $description = 'Clear last month traffic cache/totals so the new month starts at 0 GB';

    public function handle(CustomerTrafficUsageService $usage): int
    {
        $n = $usage->resetEndedMonths();
        $this->info("Reset monthly traffic for {$n} user(s).");

        return self::SUCCESS;
    }
}
