<?php

namespace App\Console\Commands;

use App\Models\Payment;
use App\Services\Collector\CollectorSettlementService;
use App\Services\Collector\CollectorStaffResolver;
use App\Support\PaymentType;
use Illuminate\Console\Command;

final class BackfillLegacyPaymentCollectorsCommand extends Command
{
    protected $signature = 'isp:backfill-payment-collectors
                            {--tenant=1 : Tenant id}
                            {--dry-run : Show counts only}';

    protected $description = 'Set recorded_by / collector rows from legacy portal received_by on payments';

    public function handle(CollectorStaffResolver $resolver, CollectorSettlementService $settlements): int
    {
        $tenantId = (int) $this->option('tenant');
        $dryRun = (bool) $this->option('dry-run');

        $query = Payment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereIn('payment_type', [PaymentType::PAYMENT, PaymentType::WALLET_APPLY])
            ->whereNull('recorded_by')
            ->whereNotNull('meta->received_by');

        $total = (clone $query)->count();
        $this->info("Payments missing recorded_by with received_by: {$total}");

        if ($dryRun || $total === 0) {
            return self::SUCCESS;
        }

        $updated = 0;
        $collections = 0;

        $query->orderBy('id')->chunkById(200, function ($chunk) use ($resolver, $settlements, $tenantId, &$updated, &$collections): void {
            foreach ($chunk as $payment) {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $receivedBy = trim((string) ($meta['received_by'] ?? ''));
                if ($receivedBy === '') {
                    continue;
                }

                $staffId = $resolver->resolveStaffUserIdFromName($receivedBy, $tenantId);
                if ($staffId === null) {
                    continue;
                }

                $meta['collector_attributed_to'] = $staffId;
                $payment->forceFill([
                    'recorded_by' => $staffId,
                    'meta' => $meta,
                ])->saveQuietly();
                $updated++;

                if ($settlements->recordCollectionFromPayment($payment->fresh()) !== null) {
                    $collections++;
                }
            }
        });

        $this->info("Updated recorded_by: {$updated}");
        $this->info("Collector collection rows created: {$collections}");

        return self::SUCCESS;
    }
}
