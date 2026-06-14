<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Payment;
use App\Services\Billing\PaymentVoidService;
use App\Support\CustomerBalanceDue;
use App\Support\LegacyPortalDateParser;
use App\Support\PaymentCollectionSource;
use App\Support\PaymentType;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Keep local collection history aligned with legacy portal payment history (pay.anetbd.com).
 */
final class LegacyPortalCollectionReconcileService
{
    public function __construct(
        private readonly LegacyPortalBillingImporter $importer,
        private readonly PaymentVoidService $voidService,
        private readonly int $tenantId = 1,
    ) {}

    /**
     * @return array{
     *   customers: int,
     *   imported: int,
     *   skipped_import: int,
     *   legacy_portal_rows: int,
     *   local_legacy_portal: int,
     *   local_only: int,
     *   voided: int,
     *   void_blocked: int,
     *   orphan_candidates: int,
     *   errors: list<string>,
     * }
     */
    public function reconcileAll(
        LegacyPortalSessionClient $client,
        bool $importMissing = true,
        bool $voidOrphans = false,
        bool $dryRun = false,
        ?string $customerCode = null,
    ): array {
        $totals = $this->emptyStats();

        $customers = $this->importer->customersByLegacyHeaderId();
        if ($customerCode !== null && $customerCode !== '') {
            $customers = $customers->filter(
                fn (Customer $c): bool => $c->customer_code === $customerCode,
            );
        }

        $i = 0;
        foreach ($customers as $headerId => $customer) {
            if ($i > 0 && $i % 25 === 0) {
                try {
                    $client->resetSession();
                    $client->login();
                } catch (\Throwable $e) {
                    $totals['errors'][] = 'legacy portal session reset failed: '.$e->getMessage();
                }
            }
            $i++;

            $row = $this->reconcileCustomer(
                $client,
                $customer,
                (int) $headerId,
                $importMissing,
                $voidOrphans,
                $dryRun,
            );
            $totals['customers']++;
            foreach (['imported', 'skipped_import', 'legacy_portal_rows', 'local_legacy_portal', 'local_only', 'voided', 'void_blocked', 'orphan_candidates'] as $key) {
                $totals[$key] += $row[$key];
            }
            $totals['errors'] = array_merge($totals['errors'], $row['errors']);
        }

        return $totals;
    }

    /**
     * @return array{
     *   imported: int,
     *   skipped_import: int,
     *   legacy_portal_rows: int,
     *   local_legacy_portal: int,
     *   local_only: int,
     *   voided: int,
     *   void_blocked: int,
     *   orphan_candidates: int,
     *   errors: list<string>,
     * }
     */
    public function reconcileCustomer(
        LegacyPortalSessionClient $client,
        Customer $customer,
        int $customerHeaderId,
        bool $importMissing = true,
        bool $voidOrphans = false,
        bool $dryRun = false,
    ): array {
        $stats = $this->emptyStats();
        $stats['errors'] = [];

        if ($customerHeaderId <= 0) {
            $customerHeaderId = self::resolveHeaderId($customer) ?? 0;
        }

        if ($customerHeaderId <= 0) {
            $stats['errors'][] = "{$customer->customer_code}: missing legacy portal CustomerHeaderId";

            return $stats;
        }

        if ($importMissing) {
            try {
                $import = $this->importer->importCustomerPayments($client, $customer, $customerHeaderId, false);
                $stats['imported'] = $import['payments'];
                $stats['skipped_import'] = $import['skipped'];
            } catch (\Throwable $e) {
                $stats['errors'][] = "{$customer->customer_code}: import failed — {$e->getMessage()}";
            }
        }

        $ispRows = [];
        try {
            $ispRows = $this->fetchLegacyPortalReceipts($client, $customerHeaderId);
            $stats['legacy_portal_rows'] = count($ispRows);
        } catch (\Throwable $e) {
            $stats['errors'][] = "{$customer->customer_code}: remote history — {$e->getMessage()}";
            $stats['legacy_portal_rows'] = 0;
        }

        $local = Payment::query()
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->with('invoice:id,issue_date,total,invoice_number')
            ->orderBy('paid_at')
            ->get();

        $stats['local_legacy_portal'] = $local->filter(
            fn (Payment $p): bool => PaymentCollectionSource::isLegacyPortalImport($p),
        )->count();

        $stats['local_only'] = $local->filter(
            fn (Payment $p): bool => ! PaymentCollectionSource::isLegacyPortalImport($p),
        )->count();

        $orphans = $this->detectOrphanLocalPayments($customer, $local, $ispRows);
        $stats['orphan_candidates'] = count($orphans);

        if ($voidOrphans && $orphans !== []) {
            foreach ($orphans as $payment) {
                $reason = (string) ($payment->meta['reconcile_void_reason'] ?? 'Duplicate local entry — legacy portal is source of truth');
                if ($dryRun) {
                    $stats['voided']++;

                    continue;
                }

                if (! $this->voidService->canVoid($payment)) {
                    $stats['void_blocked']++;

                    continue;
                }

                try {
                    $this->voidService->void($payment, $reason);
                    $stats['voided']++;
                } catch (\Throwable $e) {
                    $stats['void_blocked']++;
                    $stats['errors'][] = "{$customer->customer_code} payment #{$payment->id}: {$e->getMessage()}";
                }
            }

            if (! $dryRun) {
                CustomerBalanceDue::refreshMetaAfterPayment($customer->fresh());
            }
        }

        if (! $dryRun) {
            $meta = is_array($customer->meta) ? $customer->meta : [];
            $meta['legacy_portal_collections_synced_at'] = now()->toIso8601String();
            $meta['legacy_portal_collection_parity'] = [
                'legacy_portal_rows' => $stats['legacy_portal_rows'],
                'local_legacy_portal' => $stats['local_legacy_portal'],
                'local_only' => $stats['local_only'],
            ];
            $customer->forceFill(['meta' => $meta])->saveQuietly();
        }

        return $stats;
    }

    public static function resolveHeaderId(Customer $customer): ?int
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $raw = is_array($meta['legacy_portal_raw'] ?? null) ? $meta['legacy_portal_raw'] : [];
        $id = (int) ($raw['CustomerHeaderId'] ?? $meta['legacy_id'] ?? 0);

        return $id > 0 ? $id : null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function fetchLegacyPortalReceipts(LegacyPortalSessionClient $client, int $headerId): array
    {
        $receipts = [];
        $start = 0;
        $length = 200;

        do {
            $page = $client->fetchPaymentHistoryPage($headerId, $start, $length);
            foreach ($page['data'] as $row) {
                if (filter_var($row['IsBillReceiveCanceled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $receipt = trim((string) ($row['MoneyReceiptNo'] ?? ''));
                if ($receipt === '') {
                    $receipt = 'ISD-BH-'.(string) ($row['BillHeaderId'] ?? uniqid());
                }

                $receipts[$receipt] = $row;
            }

            $start += $length;
            $total = (int) ($page['iTotalDisplayRecords'] ?? 0);
        } while ($start < $total && $page['data'] !== []);

        return $receipts;
    }

    /**
     * Local desk / wallet rows that should not appear when legacy portal already recorded the collection.
     *
     * @param  array<string, array<string, mixed>>  $ispReceipts
     * @return list<Payment>
     */
    private function detectOrphanLocalPayments(Customer $customer, Collection $local, array $ispReceipts): array
    {
        $legacyPortalPayments = $local->filter(
            fn (Payment $p): bool => PaymentCollectionSource::isLegacyPortalImport($p),
        );

        $byInvoiceMonth = $this->groupPaymentsByBillMonth($legacyPortalPayments);

        $orphans = [];
        $orphanIds = [];

        foreach ($this->duplicateWalletPayments($local) as $payment) {
            $orphanIds[$payment->id] = true;
            $orphans[] = $payment;
        }

        foreach ($this->duplicateDeskPayments($local) as $payment) {
            $orphanIds[$payment->id] = true;
            $orphans[] = $payment;
        }

        $portalAmountByMonth = $this->buildPortalAmountMonthIndex($ispReceipts);

        foreach ($local as $payment) {
            if (isset($orphanIds[$payment->id])) {
                continue;
            }
            if (PaymentCollectionSource::isLegacyPortalImport($payment)) {
                continue;
            }

            if (($payment->payment_type ?? PaymentType::PAYMENT) === PaymentType::REFUND) {
                continue;
            }

            $meta = is_array($payment->meta) ? $payment->meta : [];
            if (($meta['gateway_webhook'] ?? false) === true) {
                continue;
            }

            $reason = $this->orphanReason($payment, $legacyPortalPayments, $byInvoiceMonth, $portalAmountByMonth);
            if ($reason === null) {
                continue;
            }

            $meta['reconcile_void_reason'] = $reason;
            $payment->meta = $meta;
            $orphanIds[$payment->id] = true;
            $orphans[] = $payment;
        }

        return $orphans;
    }

    /**
     * @return list<Payment>
     */
    private function duplicateWalletPayments(Collection $local): array
    {
        /** @var array<string, list<Payment>> $groups */
        $groups = [];

        foreach ($local as $payment) {
            if (($payment->payment_type ?? PaymentType::PAYMENT) !== PaymentType::WALLET_APPLY) {
                continue;
            }
            if (PaymentCollectionSource::isLegacyPortalImport($payment)) {
                continue;
            }

            $day = $payment->paid_at?->format('Y-m-d') ?? 'unknown';
            $key = (string) ($payment->invoice_id ?? 'none').'|'.$day.'|'.number_format((float) $payment->amount, 2, '.', '');
            $groups[$key] ??= [];
            $groups[$key][] = $payment;
        }

        $dupes = [];
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, fn (Payment $a, Payment $b): int => $a->id <=> $b->id);
            array_shift($group);

            foreach ($group as $payment) {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $meta['reconcile_void_reason'] = 'Duplicate wallet apply on same invoice/day';
                $payment->meta = $meta;
                $dupes[] = $payment;
            }
        }

        return $dupes;
    }

    /**
     * @param  Collection<int, Payment>  $legacyPortalPayments
     * @param  array<string, list<Payment>>  $byInvoiceMonth
     * @param  array<string, true>  $portalAmountByMonth
     */
    private function orphanReason(
        Payment $local,
        Collection $legacyPortalPayments,
        array $byInvoiceMonth,
        array $portalAmountByMonth,
    ): ?string {
        $type = (string) ($local->payment_type ?? PaymentType::PAYMENT);

        if ($type === PaymentType::WALLET_APPLY) {
            return $this->orphanWalletReason($local, $legacyPortalPayments, $byInvoiceMonth);
        }

        if ($type === PaymentType::PAYMENT) {
            return $this->orphanDeskPaymentReason($local, $byInvoiceMonth, $portalAmountByMonth);
        }

        return null;
    }

    /**
     * @param  Collection<int, Payment>  $legacyPortalPayments
     * @param  array<string, list<Payment>>  $byInvoiceMonth
     */
    private function orphanWalletReason(
        Payment $wallet,
        Collection $legacyPortalPayments,
        array $byInvoiceMonth,
    ): ?string {
        $billMonth = $this->canonicalBillMonth($wallet);
        if ($billMonth === null || ! isset($byInvoiceMonth[$billMonth])) {
            return null;
        }

        /** @var list<Payment> $sameMonth */
        $sameMonth = $byInvoiceMonth[$billMonth];
        $ispPaid = round(collect($sameMonth)->sum(fn (Payment $p): float => (float) $p->amount), 2);
        $invoice = $wallet->invoice;
        $invoiceTotal = $invoice !== null ? round((float) $invoice->total, 2) : 0.0;

        if ($invoiceTotal > 0 && $ispPaid >= $invoiceTotal - 0.009) {
            return 'Wallet apply removed — legacy portal already recorded full payment for this bill month';
        }

        $walletDay = $wallet->paid_at?->format('Y-m-d');
        return null;
    }

    /**
     * @param  array<string, list<Payment>>  $byInvoiceMonth
     * @param  array<string, true>  $portalAmountByMonth
     */
    private function orphanDeskPaymentReason(Payment $local, array $byInvoiceMonth, array $portalAmountByMonth): ?string
    {
        $localAmount = round((float) $local->amount, 2);
        $billMonth = $this->canonicalBillMonth($local);

        if ($billMonth !== null) {
            $remoteKey = $billMonth.'|'.number_format($localAmount, 2, '.', '');
            if (isset($portalAmountByMonth[$remoteKey])) {
                return 'Local desk collection removed — legacy portal already has this amount for '.$billMonth;
            }
        }

        if ($billMonth === null || ! isset($byInvoiceMonth[$billMonth])) {
            return null;
        }

        /** @var list<Payment> $ispMonth */
        $ispMonth = $byInvoiceMonth[$billMonth];

        foreach ($ispMonth as $isp) {
            if (abs((float) $isp->amount - $localAmount) < 0.01) {
                return 'Local desk collection removed — same amount already imported from legacy portal ('.$isp->receipt_number.')';
            }
        }

        $ispSum = round(collect($ispMonth)->sum(fn (Payment $p): float => (float) $p->amount), 2);
        if ($localAmount > $ispSum + 0.009 && $ispSum > 0) {
            return null;
        }

        if ($local->invoice_id !== null) {
            $invoice = $local->invoice;
            if ($invoice !== null && $ispSum >= (float) $invoice->total - 0.009) {
                return 'Local desk collection removed — legacy portal already paid this invoice month';
            }
        }

        return null;
    }

    /**
     * @return list<Payment>
     */
    private function duplicateDeskPayments(Collection $local): array
    {
        /** @var array<string, list<Payment>> $groups */
        $groups = [];

        foreach ($local as $payment) {
            if (($payment->payment_type ?? PaymentType::PAYMENT) !== PaymentType::PAYMENT) {
                continue;
            }
            if (PaymentCollectionSource::isLegacyPortalImport($payment)) {
                continue;
            }

            $day = $payment->paid_at?->format('Y-m-d') ?? 'unknown';
            $key = $day.'|'.number_format((float) $payment->amount, 2, '.', '').'|'.strtolower((string) $payment->method);
            $groups[$key] ??= [];
            $groups[$key][] = $payment;
        }

        $dupes = [];
        foreach ($groups as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, fn (Payment $a, Payment $b): int => $a->id <=> $b->id);
            array_shift($group);

            foreach ($group as $payment) {
                $meta = is_array($payment->meta) ? $payment->meta : [];
                $meta['reconcile_void_reason'] = 'Duplicate desk collection (same day, amount, method)';
                $payment->meta = $meta;
                $dupes[] = $payment;
            }
        }

        return $dupes;
    }

    /**
     * @param  Collection<int, Payment>  $payments
     * @return array<string, list<Payment>>
     */
    private function groupPaymentsByBillMonth(Collection $payments): array
    {
        $by = [];
        foreach ($payments as $payment) {
            $month = $this->canonicalBillMonth($payment);
            if ($month === null) {
                continue;
            }
            $by[$month] ??= [];
            $by[$month][] = $payment;
        }

        return $by;
    }

    /**
     * @param  array<string, array<string, mixed>>  $ispReceipts
     * @return array<string, true>
     */
    private function buildPortalAmountMonthIndex(array $ispReceipts): array
    {
        $index = [];
        foreach ($ispReceipts as $row) {
            $amount = round((float) ($row['PaidAmount'] ?? 0), 2);
            if ($amount <= 0) {
                continue;
            }
            $month = $this->canonicalBillMonthFromIspRow($row);
            if ($month === null) {
                continue;
            }
            $index[$month.'|'.number_format($amount, 2, '.', '')] = true;
        }

        return $index;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function canonicalBillMonthFromIspRow(array $row): ?string
    {
        $billMonth = trim((string) ($row['BillMonth'] ?? ''));
        if ($billMonth !== '') {
            $parsed = $this->parseBillMonthString($billMonth);
            if ($parsed !== null) {
                return $parsed->format('Y-m');
            }
        }

        foreach (['PaymentDate', 'BillReceivedDate'] as $key) {
            $parsed = LegacyPortalDateParser::parse($row[$key] ?? null);
            if ($parsed !== null) {
                return $parsed->format('Y-m');
            }
        }

        return null;
    }

    private function parseBillMonthString(string $billMonth): ?Carbon
    {
        return LegacyPortalDateParser::parseBillMonth($billMonth);
    }

    private function canonicalBillMonth(Payment $payment): ?string
    {
        $invoice = $payment->relationLoaded('invoice') ? $payment->invoice : null;
        if ($invoice?->issue_date !== null) {
            return $invoice->issue_date->format('Y-m');
        }

        $meta = is_array($payment->meta) ? $payment->meta : [];
        $billMonth = trim((string) ($meta['legacy_portal_bill_month'] ?? ''));
        if ($billMonth !== '') {
            $parsed = $this->parseBillMonthString($billMonth);

            return $parsed?->format('Y-m');
        }

        return $payment->paid_at?->format('Y-m');
    }

    /**
     * @return array{
     *   customers: int,
     *   imported: int,
     *   skipped_import: int,
     *   legacy_portal_rows: int,
     *   local_legacy_portal: int,
     *   local_only: int,
     *   voided: int,
     *   void_blocked: int,
     *   orphan_candidates: int,
     *   errors: list<string>,
     * }
     */
    private function emptyStats(): array
    {
        return [
            'customers' => 0,
            'imported' => 0,
            'skipped_import' => 0,
            'legacy_portal_rows' => 0,
            'local_legacy_portal' => 0,
            'local_only' => 0,
            'voided' => 0,
            'void_blocked' => 0,
            'orphan_candidates' => 0,
            'errors' => [],
        ];
    }
}
