<?php

namespace App\Console\Commands;

use App\Services\Payments\GatewayPaymentVerificationService;
use Illuminate\Console\Command;

final class MfsMatchPendingPaymentsCommand extends Command
{
    protected $signature = 'mfs:match-pending-payments {--tenant= : Limit to tenant id}';

    protected $description = 'Auto-approve pending personal MFS payments when matching SMS is already in the ledger (late SMS / rescan).';

    public function handle(GatewayPaymentVerificationService $verification): int
    {
        if (! (bool) config('mfs_personal.sms_ingest.enabled', false)) {
            $this->warn('SMS ingest is disabled — nothing to do.');

            return self::SUCCESS;
        }

        $tenantId = $this->option('tenant');
        $matched = $verification->retryAllPendingMatches(
            $tenantId !== null ? (int) $tenantId : null,
        );

        $this->info("Matched and auto-approved {$matched} pending payment(s).");

        return self::SUCCESS;
    }
}
