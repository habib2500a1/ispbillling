<?php

namespace App\Console\Commands;

use App\Services\Saas\SaasBillingService;
use Illuminate\Console\Command;

class LockOverdueSaas extends Command
{
    protected $signature = 'saas:lock-overdue';

    protected $description = 'Lock ISP operators whose SaaS invoices are unpaid past due date';

    public function handle(SaasBillingService $billing): int
    {
        $locked = $billing->lockOverdue();
        $this->info("Locked {$locked} overdue SaaS operator(s).");

        return self::SUCCESS;
    }
}
