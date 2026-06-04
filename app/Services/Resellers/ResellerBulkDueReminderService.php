<?php

namespace App\Services\Resellers;

use App\Models\Invoice;
use App\Models\Reseller;

final class ResellerBulkDueReminderService
{
    public function __construct(
        private readonly ResellerCustomerDueReminderService $reminders,
    ) {}

    /**
     * @return array{sent: int, skipped: int, invoices: int}
     */
    public function runForReseller(Reseller $reseller, bool $dryRun = false, ?int $minDaysOverdue = null): array
    {
        if (! config('reseller_billing.due_reminders.bulk_enabled', true)) {
            return ['sent' => 0, 'skipped' => 0, 'invoices' => 0];
        }

        $minDaysOverdue ??= (int) config('reseller_billing.due_reminders.bulk_min_days_overdue', 0);
        $cutoff = now()->startOfDay()->subDays(max(0, $minDaysOverdue));

        $sent = 0;
        $skipped = 0;
        $invoices = 0;

        $query = Invoice::query()
            ->with('customer')
            ->whereHas('customer', fn ($q) => $q
                ->where('reseller_id', $reseller->id)
                ->where('status', 'active'))
            ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
            ->whereRaw('(total - amount_paid) > 0.009')
            ->where(function ($q) use ($cutoff): void {
                $q->whereDate('due_date', '<=', $cutoff)
                    ->orWhereNull('due_date');
            })
            ->orderBy('due_date')
            ->orderBy('id');

        foreach ($query->cursor() as $invoice) {
            /** @var Invoice $invoice */
            $invoices++;

            if ($dryRun) {
                $sent++;

                continue;
            }

            if ($this->reminders->sendForInvoice($invoice, $reseller)) {
                $sent++;
            } else {
                $skipped++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped, 'invoices' => $invoices];
    }

    /**
     * @return array{sent: int, skipped: int, resellers: int, invoices: int}
     */
    public function runAll(?int $tenantId = null, bool $dryRun = false, ?int $minDaysOverdue = null): array
    {
        $sent = 0;
        $skipped = 0;
        $invoices = 0;
        $resellers = 0;

        $query = Reseller::query()->withoutGlobalScopes()->where('is_active', true);
        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        $query->orderBy('id')->chunkById(25, function ($chunk) use ($dryRun, $minDaysOverdue, &$sent, &$skipped, &$invoices, &$resellers): void {
            foreach ($chunk as $reseller) {
                $result = $this->runForReseller($reseller, $dryRun, $minDaysOverdue);
                if ($result['invoices'] === 0) {
                    continue;
                }

                $resellers++;
                $sent += $result['sent'];
                $skipped += $result['skipped'];
                $invoices += $result['invoices'];
            }
        });

        return [
            'sent' => $sent,
            'skipped' => $skipped,
            'resellers' => $resellers,
            'invoices' => $invoices,
        ];
    }
}
