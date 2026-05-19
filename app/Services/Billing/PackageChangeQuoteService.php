<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Package;
final class PackageChangeQuoteService
{
    /**
     * @return array{
     *   current_package: string,
     *   new_package: string,
     *   is_upgrade: bool,
     *   days_remaining: int,
     *   credit_amount: float,
     *   new_charge: float,
     *   net_due: float,
     *   effective_label: string
     * }
     */
    public function quote(Customer $customer, Package $newPackage): array
    {
        $current = $customer->package;
        $today = now()->startOfDay();

        if ($current === null) {
            $charge = PackagePriceResolver::resolveCyclePrice($newPackage, $customer, $today);

            return [
                'current_package' => '—',
                'new_package' => $newPackage->name,
                'is_upgrade' => true,
                'days_remaining' => 0,
                'credit_amount' => 0.0,
                'new_charge' => $charge,
                'net_due' => $charge,
                'effective_label' => 'Immediate',
            ];
        }

        [$periodStart, $periodEnd] = BillingPeriodResolver::resolve($current, $today);
        $daysRemaining = max(0, $today->diffInDays($periodEnd->copy()->startOfDay()) + 1);
        $totalDays = max(1, $periodStart->copy()->startOfDay()->diffInDays($periodEnd->copy()->startOfDay()) + 1);

        $currentCycle = PackagePriceResolver::resolveCyclePrice($current, $customer, $today);
        $newCycle = PackagePriceResolver::resolveCyclePrice($newPackage, $customer, $today);

        $credit = ProrationService::proratedAmount(
            $currentCycle,
            $periodStart,
            $periodEnd,
            $today,
            $periodEnd,
        );

        $newCharge = ProrationService::proratedAmount(
            $newCycle,
            $periodStart,
            $periodEnd,
            $today,
            $periodEnd,
        );

        $netDue = round(max(0, $newCharge - $credit), 2);
        $isUpgrade = $newCycle > $currentCycle || $newPackage->download_mbps > $current->download_mbps;

        return [
            'current_package' => $current->name,
            'new_package' => $newPackage->name,
            'is_upgrade' => $isUpgrade,
            'days_remaining' => $daysRemaining,
            'credit_amount' => round($credit, 2),
            'new_charge' => round($newCharge, 2),
            'net_due' => $netDue,
            'effective_label' => $isUpgrade && config('billing.portal_instant_upgrade', true)
                ? 'Pay prorated difference now'
                : 'Next billing cycle',
        ];
    }

    /**
     * Create an open invoice for mid-cycle upgrade difference.
     */
    public function createUpgradeInvoice(Customer $customer, Package $newPackage): ?\App\Models\Invoice
    {
        $quote = $this->quote($customer, $newPackage);
        if ($quote['net_due'] <= 0) {
            return null;
        }

        $invoice = \App\Models\Invoice::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(min(3, (int) ($customer->grace_period_days ?? 3)))->toDateString(),
            'period_start' => now()->toDateString(),
            'period_end' => now()->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'sd_amount' => 0,
            'withholding_amount' => 0,
            'discount_amount' => 0,
            'coupon_discount_amount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'status' => 'open',
            'notes' => 'Package upgrade: '.$quote['current_package'].' → '.$quote['new_package'],
        ]);

        $sort = 0;
        if ($quote['credit_amount'] > 0) {
            \App\Models\InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'item_type' => 'package_credit',
                'description' => 'Credit — unused '.$quote['current_package'].' ('.$quote['days_remaining'].' days)',
                'quantity' => 1,
                'unit_price' => -$quote['credit_amount'],
                'line_total' => 0,
                'sort_order' => $sort++,
            ]);
        }

        \App\Models\InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'item_type' => 'package_upgrade',
            'description' => 'Upgrade to '.$newPackage->name.' (prorated)',
            'quantity' => 1,
            'unit_price' => $quote['new_charge'],
            'line_total' => 0,
            'sort_order' => $sort++,
            'meta' => ['target_package_id' => $newPackage->id],
        ]);

        InvoiceCalculator::recalculate($invoice->fresh());

        return $invoice->fresh();
    }

    public function applyPackageChange(Customer $customer, Package $newPackage): void
    {
        $customer->forceFill(['package_id' => $newPackage->id])->save();
    }
}
