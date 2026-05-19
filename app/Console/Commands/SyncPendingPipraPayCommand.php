<?php

namespace App\Console\Commands;

use App\Services\Payments\PipraPayCheckoutService;
use App\Services\Payments\PipraPayPendingSyncService;
use Illuminate\Console\Command;

class SyncPendingPipraPayCommand extends Command
{
    protected $signature = 'piprapay:sync-pending {--limit=50 : Max pending rows to check}';

    protected $description = 'Poll PipraPay for completed payments (e.g. after manual approval in merchant panel)';

    public function handle(PipraPayPendingSyncService $sync): int
    {
        if (! PipraPayCheckoutService::isEnabled()) {
            $this->warn('PipraPay is disabled.');

            return self::SUCCESS;
        }

        $result = $sync->sync((int) $this->option('limit'));

        $this->info(sprintf(
            'Checked %d · recorded %d · still pending %d · errors %d',
            $result['checked'],
            $result['recorded'],
            $result['still_pending'],
            $result['errors'],
        ));

        foreach ($result['messages'] as $message) {
            $this->line('  '.$message);
        }

        return self::SUCCESS;
    }
}
