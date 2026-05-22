<?php

namespace App\Services\Billing;

use App\Models\CollectorCollection;
use App\Models\CollectorExpense;
use App\Models\CollectorSettlement;
use App\Models\StaffExpense;
use App\Models\JournalEntry;
use App\Models\Payment;
use App\Models\VendorPayment;
use App\Support\PaymentAllocationBreakdown;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class BillingFundFlowService
{
    public function __construct(
        private readonly CollectionDeskReportService $collections,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function report(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $collectorId = null,
        ?string $search = null,
        ?int $tenantId = null,
        bool $includeCompanyExpenses = false,
    ): array {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $from = ($from ?? now())->copy()->startOfDay();
        $to = ($to ?? now())->copy()->endOfDay();

        $base = $this->collections->report($from, $to, $collectorId, $search, $tenantId);

        $payments = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->when($collectorId, fn ($q) => $q->where('recorded_by', $collectorId))
            ->get();

        $toInvoice = 0.0;
        $toWallet = 0.0;
        $fromWallet = 0.0;
        $walletDeposits = 0.0;
        $refunds = 0.0;
        $cashCollections = 0.0;

        foreach ($payments as $payment) {
            $breakdown = PaymentAllocationBreakdown::for($payment);
            $toInvoice += $breakdown['to_invoice'];
            $toWallet += $breakdown['to_wallet'];
            $fromWallet += $breakdown['from_wallet'];

            if ($payment->payment_type === PaymentType::WALLET_DEPOSIT) {
                $walletDeposits += (float) $payment->amount;
            }
            if ($payment->payment_type === PaymentType::REFUND) {
                $refunds += (float) $payment->amount;
            }
            if (in_array((string) $payment->method, [PaymentGateway::CASH, PaymentGateway::BANK, PaymentGateway::OTHER], true)
                && $payment->payment_type === PaymentType::PAYMENT) {
                $cashCollections += (float) $payment->amount;
            }
        }

        $collectionsInPeriod = CollectorCollection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('collected_at', [$from, $to])
            ->when($collectorId, fn ($q) => $q->where('collector_id', $collectorId))
            ->get();

        $fieldCollected = round((float) $collectionsInPeriod->sum('amount'), 2);
        $fieldSettled = round((float) $collectionsInPeriod->sum('amount_settled'), 2);
        $fieldInHand = round(max(0, $fieldCollected - $fieldSettled), 2);

        $settlementsSubmitted = round((float) CollectorSettlement::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('submitted_at', [$from, $to])
            ->when($collectorId, fn ($q) => $q->where('collector_id', $collectorId))
            ->whereIn('status', ['pending', 'approved'])
            ->sum('amount'), 2);

        $collectorExpenses = CollectorExpense::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'approved')
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->when($collectorId, fn ($q) => $q->where('collector_id', $collectorId))
            ->with('category:id,name')
            ->get();

        $expenseByCategory = $collectorExpenses
            ->groupBy(fn (CollectorExpense $e) => $e->category?->name ?? 'Other')
            ->map(fn ($group, string $name): array => [
                'category' => $name,
                'total' => round((float) $group->sum('amount'), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $collectorExpenseTotal = round((float) $collectorExpenses->sum('amount'), 2);

        $staffExpenses = StaffExpense::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', StaffExpense::STATUS_APPROVED)
            ->whereBetween('expense_date', [$from->toDateString(), $to->toDateString()])
            ->with(['category:id,name', 'vendor:id,name'])
            ->get();

        $staffExpenseByCategory = $staffExpenses
            ->groupBy(fn (StaffExpense $e): string => ($e->sourceLabel()).' · '.($e->category?->name ?? 'Other'))
            ->map(fn ($group, string $name): array => [
                'category' => $name,
                'total' => round((float) $group->sum('amount'), 2),
                'count' => $group->count(),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        $staffExpenseTotal = round((float) $staffExpenses->sum('amount'), 2);

        $vendorExpenseTotal = 0.0;
        $vendorByCategory = [];
        if ($includeCompanyExpenses) {
            $vendorPayments = VendorPayment::query()
                ->withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('status', 'completed')
                ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
                ->with('vendor:id,name')
                ->get();

            $vendorExpenseTotal = round((float) $vendorPayments->sum('amount'), 2);
            $vendorByCategory = $vendorPayments
                ->groupBy(fn (VendorPayment $p) => $p->vendor?->name ?? 'Vendor')
                ->map(fn ($group, string $name): array => [
                    'category' => $name,
                    'total' => round((float) $group->sum('amount'), 2),
                    'count' => $group->count(),
                ])
                ->sortByDesc('total')
                ->values()
                ->all();
        }

        $paymentIds = $payments->pluck('id')->filter()->values();
        $collectionByPayment = CollectorCollection::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('payment_id', $paymentIds)
            ->get()
            ->keyBy('payment_id');

        $journalByPayment = JournalEntry::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('source_type', 'customer_payment')
            ->whereIn('source_id', $paymentIds)
            ->get()
            ->keyBy('source_id');

        $detailedRows = [];
        foreach ($base['rows'] as $row) {
            $paymentId = (int) ($row['id'] ?? 0);
            $payment = $payments->firstWhere('id', $paymentId);
            $breakdown = $payment ? PaymentAllocationBreakdown::for($payment) : null;
            $collection = $collectionByPayment->get($paymentId);
            $journal = $journalByPayment->get($paymentId);

            $cashStatus = 'Office / gateway';
            if ($collection) {
                $unsettled = round((float) $collection->amount - (float) $collection->amount_settled, 2);
                $cashStatus = $unsettled > 0.009
                    ? 'With collector ('.number_format($unsettled, 2).' BDT)'
                    : 'Deposited to office';
            } elseif ($payment && in_array((string) $payment->method, [PaymentGateway::CASH, PaymentGateway::BANK], true)) {
                $cashStatus = 'Direct to office';
            }

            $detailedRows[] = array_merge($row, [
                'payment_type' => $breakdown['payment_type_label'] ?? '—',
                'to_invoice' => $breakdown['to_invoice'] ?? 0,
                'to_wallet' => $breakdown['to_wallet'] ?? 0,
                'from_wallet' => $breakdown['from_wallet'] ?? 0,
                'destination' => $breakdown['destination_label'] ?? '—',
                'destination_badge' => $breakdown['destination_badge'] ?? 'other',
                'cash_status' => $cashStatus,
                'has_journal' => $journal !== null,
                'journal_number' => $journal?->entry_number,
            ]);
        }

        $totalExpenses = round($collectorExpenseTotal + $staffExpenseTotal + $vendorExpenseTotal, 2);
        $netAfterExpenses = round((float) $base['total'] - $totalExpenses, 2);

        $currentCashInHand = 0.0;
        if ($collectorId) {
            $wallet = app(\App\Services\Collector\CollectorWalletService::class)->wallet($collectorId, $tenantId);
            $currentCashInHand = (float) ($wallet['cash_in_hand'] ?? 0);
        }

        return [
            'from' => $base['from'],
            'to' => $base['to'],
            'summary' => [
                'total_collected' => $base['total'],
                'payment_count' => $base['count'],
                'to_invoice' => round($toInvoice, 2),
                'to_wallet' => round($toWallet, 2),
                'from_wallet' => round($fromWallet, 2),
                'wallet_deposits' => round($walletDeposits, 2),
                'refunds' => round($refunds, 2),
                'cash_collections' => round($cashCollections, 2),
                'field_collected' => $fieldCollected,
                'field_in_collector_hand' => $fieldInHand,
                'field_deposited_period' => $fieldSettled,
                'settlements_submitted' => $settlementsSubmitted,
                'collector_expenses' => $collectorExpenseTotal,
                'staff_expenses' => $staffExpenseTotal,
                'vendor_expenses' => $vendorExpenseTotal,
                'total_expenses' => $totalExpenses,
                'net_after_expenses' => $netAfterExpenses,
                'current_collector_cash' => $currentCashInHand,
            ],
            'by_method' => $base['by_method'],
            'by_collector' => $base['by_collector'],
            'expenses_by_category' => $expenseByCategory,
            'staff_expenses_by_category' => $staffExpenseByCategory,
            'vendor_expenses' => $vendorByCategory,
            'rows' => $detailedRows,
            'flow_steps' => $this->flowSteps(
                (float) $base['total'],
                round($toInvoice, 2),
                round($toWallet, 2),
                $fieldInHand,
                $fieldSettled,
                $collectorExpenseTotal,
            ),
        ];
    }

    /**
     * @return list<array{label: string, amount: float, hint: string}>
     */
    private function flowSteps(
        float $collected,
        float $toInvoice,
        float $toWallet,
        float $inCollectorHand,
        float $deposited,
        float $expenses,
    ): array {
        return [
            [
                'label' => '১. ক্লায়েন্ট থেকে সংগ্রহ',
                'amount' => $collected,
                'hint' => 'সব completed payment (cash, bKash, wallet ইত্যাদি)',
            ],
            [
                'label' => '২. বিল / ইনভয়েসে জমা',
                'amount' => $toInvoice,
                'hint' => 'invoice_applied — বাকি বিল কমেছে',
            ],
            [
                'label' => '৩. সাবস্ক্রাইবার wallet-এ',
                'amount' => $toWallet,
                'hint' => 'অতিরিক্ত টাকা customer account_balance-এ',
            ],
            [
                'label' => '৪. এখনও collector-এর কাছে (cash)',
                'amount' => $inCollectorHand,
                'hint' => 'জমা দেওয়ার আগে হাতে আছে',
            ],
            [
                'label' => '৫. অফিসে জমা (period)',
                'amount' => $deposited,
                'hint' => 'collector settlement approved',
            ],
            [
                'label' => '৬. খরচ (field expense)',
                'amount' => $expenses,
                'hint' => 'approved collector expenses — cash থেকে কাটা',
            ],
        ];
    }

    /**
     * @return Collection<int, User>
     */
    public function collectorsForFilter(?int $tenantId = null): Collection
    {
        return $this->collections->collectorsForFilter($tenantId);
    }
}
