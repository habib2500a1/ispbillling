<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\CustomerLineGraceService;
use App\Support\CustomerBalanceDue;
use App\Support\LegacyPortalSource;
use Carbon\Carbon;

/**
 * Overdue rules aligned with legacy portal: billing-last-day grace and synced BalanceDue,
 * not stale duplicate local ISD history rows alone.
 */
final class LegacyPortalOverdueEvaluator
{
    public function hasOverdueOpenBalance(Customer $customer): bool
    {
        if (CustomerLineGraceService::hasActiveLineGrace($customer)) {
            return false;
        }

        if (LegacyPortalSource::isImportedSource($customer->import_source ?? null)) {
            return $this->hasLegacyPortalOverdue($customer);
        }

        return $this->hasOverdueInvoicesByDueDate($customer);
    }

    /**
     * legacy portal: bill-day grace + synced BalanceDue only — not duplicate local ISD history.
     */
    private function hasLegacyPortalOverdue(Customer $customer): bool
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];

        if (! $this->isPastIspBillingCutoff($customer, $meta)) {
            return false;
        }

        $ispDue = isset($meta['legacy_portal_balance_due'])
            ? round((float) $meta['legacy_portal_balance_due'], 2)
            : null;

        if ($ispDue !== null && filled($meta['legacy_portal_billing_synced_at'] ?? null)) {
            return $ispDue > 0.009;
        }

        return false;
    }

    public function shouldAlertOverdueOnlineSession(Customer $customer): bool
    {
        return $this->hasOverdueOpenBalance($customer);
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function isPastIspBillingCutoff(Customer $customer, array $meta): bool
    {
        $billingDay = (int) ($meta['legacy_portal_billing_last_day'] ?? $customer->billing_day ?? 0);
        if ($billingDay < 1 || $billingDay > 31) {
            return true;
        }

        $cutoff = now()->copy()
            ->day(min($billingDay, (int) now()->daysInMonth()))
            ->startOfDay();

        return now()->startOfDay()->gt($cutoff);
    }

    private function hasOverdueInvoicesByDueDate(Customer $customer): bool
    {
        $graceDays = max(0, (int) config('network.auto_suspend_grace_days', 0));
        $minBalance = max(0.0, (float) config('network.auto_suspend_min_balance', 1));
        $asOf = now()->subDays($graceDays);

        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->when($customer->tenant_id !== null, fn ($q) => $q->where('tenant_id', $customer->tenant_id))
            ->whereIn('status', CustomerBalanceDue::OPEN_INVOICE_STATUSES)
            ->get()
            ->contains(fn (Invoice $invoice): bool => $invoice->balanceDue() >= $minBalance
                && $invoice->due_date !== null
                && $asOf->toDateString() > $invoice->due_date->toDateString());
    }

    /**
     * Apply line grace from legacy portal billing row (BillingLastDate / EffectiveTo).
     *
     * @param  array<string, mixed>  $row
     */
    public function syncLineGraceFromBillingRow(Customer $customer, array $row): void
    {
        if (filter_var($row['Disabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return;
        }

        $status = strtolower((string) ($row['Status'] ?? ''));
        if (str_contains($status, 'suspend') || str_contains($status, 'inactive')) {
            return;
        }

        $balanceDue = $this->parseMoney($row['BalanceDue'] ?? 0);
        $until = $this->resolveGraceUntil($row, $balanceDue);

        if ($until === null || $until->lt(now()->startOfDay())) {
            return;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $existing = CustomerLineGraceService::lineGraceUntil($customer);
        if ($existing !== null && $existing->gte($until)) {
            return;
        }

        $meta['line_grace_until'] = $until->toDateString();
        $meta['line_grace_from_legacy_portal'] = true;
        $meta['line_grace_extended_at'] = now()->toIso8601String();
        $customer->forceFill(['meta' => $meta])->saveQuietly();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveGraceUntil(array $row, float $balanceDue): ?Carbon
    {
        $effective = trim((string) ($row['EffectiveTo'] ?? ''));
        if ($effective !== '') {
            try {
                $parsed = Carbon::parse($effective)->startOfDay();
                if ($parsed->year >= 2000) {
                    return $parsed->gt(now()->copy()->endOfMonth()) ? now()->endOfMonth()->startOfDay() : $parsed;
                }
            } catch (\Throwable) {
                // fall through
            }
        }

        $billingDay = (int) preg_replace('/\D+/', '', (string) ($row['BillingLastDate'] ?? ''));
        if ($billingDay >= 1 && $billingDay <= 31) {
            $cutoff = now()->copy()
                ->day(min($billingDay, (int) now()->daysInMonth()))
                ->startOfDay();

            if ($balanceDue > 0.009 && now()->startOfDay()->lte($cutoff)) {
                return $cutoff;
            }

            if ($balanceDue > 0.009 && now()->startOfDay()->gt($cutoff)) {
                return now()->endOfMonth()->startOfDay();
            }
        }

        if ($balanceDue > 0.009) {
            return now()->endOfMonth()->startOfDay();
        }

        return null;
    }

    private function parseMoney(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^\d.]/', '', (string) $value) ?? '';

        return round((float) ($clean !== '' ? $clean : 0), 2);
    }
}
