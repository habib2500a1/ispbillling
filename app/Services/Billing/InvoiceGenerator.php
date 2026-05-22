<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Device;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Package;
use App\Support\BillingCycleType;
use Carbon\Carbon;
use Carbon\CarbonInterface;

final class InvoiceGenerator
{
    public static function shouldBillOnDate(Customer $customer, Package $package, CarbonInterface $date, bool $force = false): bool
    {
        if ($force) {
            return true;
        }

        $type = $package->billing_cycle_type ?? BillingCycleType::MONTHLY;

        return match ($type) {
            BillingCycleType::HOURLY, BillingCycleType::DAILY => true,
            BillingCycleType::MONTHLY, BillingCycleType::DAYS_30,
            BillingCycleType::QUARTERLY, BillingCycleType::HALF_YEARLY, BillingCycleType::YEARLY
                => (int) $customer->billing_day === (int) $date->day,
            default => (int) $customer->billing_day === (int) $date->day,
        };
    }

    /**
     * @return Invoice|null Created invoice, or null if skipped
     */
    public static function generateForCustomer(
        Customer $customer,
        CarbonInterface $referenceDate,
        bool $noProrate = false,
        ?string $couponCode = null,
    ): ?Invoice {
        if (! $customer->shouldGenerateInvoice()) {
            return null;
        }

        $package = $customer->package;
        if ($package === null) {
            return null;
        }

        $basePriceEstimate = PackagePriceResolver::resolveCyclePrice($package, $customer, Carbon::parse($referenceDate));
        if (! app(CustomerCreditLimitService::class)->canGenerateInvoice($customer, (float) $basePriceEstimate)) {
            return null;
        }

        $date = Carbon::parse($referenceDate)->startOfDay();
        [$periodStart, $periodEnd] = BillingPeriodResolver::resolve($package, $date);

        $exists = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->whereDate('period_end', $periodEnd->toDateString())
            ->exists();

        if ($exists) {
            return null;
        }

        $basePrice = PackagePriceResolver::resolveCyclePrice($package, $customer, $date);

        if (! $noProrate && $customer->joined_at) {
            $joined = Carbon::parse($customer->joined_at)->startOfDay();
            if ($joined->gt($periodStart)) {
                $basePrice = ProrationService::proratedAmount(
                    $basePrice,
                    $periodStart,
                    $periodEnd,
                    $joined,
                );
            }
        }

        [$issueDate, $dueDate] = static::resolveIssueAndDueDates($customer, $date);

        $invoice = Invoice::createTrusted([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'period_start' => $periodStart->toDateString(),
            'period_end' => $periodEnd->toDateString(),
            'subtotal' => 0,
            'tax_amount' => 0,
            'sd_amount' => 0,
            'withholding_amount' => 0,
            'discount_amount' => 0,
            'coupon_discount_amount' => 0,
            'total' => 0,
            'amount_paid' => 0,
            'status' => 'open',
            'notes' => sprintf(
                'Auto-generated (%s, %s)',
                BillingCycleType::label($package->billing_cycle_type ?? BillingCycleType::MONTHLY),
                $customer->billing_mode ?? 'postpaid',
            ),
        ]);

        $sort = 0;
        InvoiceItem::query()->create([
            'invoice_id' => $invoice->id,
            'item_type' => 'package',
            'description' => 'Internet — '.$package->name,
            'quantity' => 1,
            'unit_price' => round($basePrice, 2),
            'line_total' => 0,
            'sort_order' => $sort++,
        ]);

        foreach ($package->addons as $addon) {
            if (! $addon->is_active || (float) $addon->price_monthly <= 0) {
                continue;
            }
            $addonPrice = PackagePriceResolver::scaleToCycle((float) $addon->price_monthly, $package);
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'item_type' => 'addon:'.$addon->addon_type,
                'description' => $addon->label,
                'quantity' => 1,
                'unit_price' => $addonPrice,
                'line_total' => 0,
                'sort_order' => $sort++,
            ]);
        }

        Device::query()
            ->where('customer_id', $customer->id)
            ->where('type', 'onu')
            ->where('lease_status', 'active')
            ->where('lease_monthly_fee', '>', 0)
            ->each(function (Device $device) use ($invoice, $package, &$sort): void {
                $fee = PackagePriceResolver::scaleToCycle((float) $device->lease_monthly_fee, $package);
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'onu_lease',
                    'description' => 'ONU lease — '.($device->display_name ?: $device->serial_number),
                    'quantity' => 1,
                    'unit_price' => $fee,
                    'line_total' => 0,
                    'sort_order' => $sort++,
                    'device_id' => $device->id,
                    'product_id' => $device->product_id,
                    'meta' => ['device_id' => $device->id],
                ]);
            });

        static::appendOneTimeFees($invoice, $customer, $package, $sort);

        $sort = app(FupOverageBillingService::class)->appendToInvoice(
            $invoice->fresh(),
            $customer,
            $package,
            $sort,
        );

        InvoiceCalculator::recalculate($invoice->fresh());

        if ($couponCode) {
            try {
                CouponApplicator::apply($invoice->fresh(), $couponCode);
            } catch (\Throwable) {
                // Coupon failure should not drop the invoice
            }
        }

        return $invoice->fresh();
    }

    /**
     * @return array{0: string, 1: string} issue_date, due_date
     */
    public static function resolveIssueAndDueDates(Customer $customer, CarbonInterface $issueReference): array
    {
        $issue = $issueReference->toDateString();
        $grace = max(0, (int) ($customer->grace_period_days ?? 10));
        $mode = $customer->billing_mode ?? 'postpaid';

        if ($mode === 'prepaid' || $mode === 'advance') {
            // Advance: pay by issue date (or short grace)
            $due = $issueReference->copy()->addDays(min($grace, 3))->toDateString();
        } else {
            // Postpaid: due after grace from issue
            $due = $issueReference->copy()->addDays($grace)->toDateString();
        }

        return [$issue, $due];
    }

    private static function appendOneTimeFees(Invoice $invoice, Customer $customer, Package $package, int &$sort): void
    {
        if (config('billing.setup_fee_on_first_invoice', true)) {
            $setupFee = round((float) ($package->setup_fee ?? 0), 2);
            if ($setupFee > 0 && ! static::customerHasSetupFee($customer)) {
                InvoiceItem::query()->create([
                    'invoice_id' => $invoice->id,
                    'item_type' => 'setup_fee',
                    'description' => 'Connection setup fee',
                    'quantity' => 1,
                    'unit_price' => $setupFee,
                    'line_total' => 0,
                    'sort_order' => $sort++,
                ]);
            }
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $installationCharge = round((float) ($meta['installation_charge'] ?? 0), 2);
        if ($installationCharge > 0 && ! static::customerHasInstallationFee($customer)) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'item_type' => 'installation_fee',
                'description' => 'Installation / line charge',
                'quantity' => 1,
                'unit_price' => $installationCharge,
                'line_total' => 0,
                'sort_order' => $sort++,
            ]);
        }

        if (config('billing.reconnection_fee_enabled', true)
            && $customer->pending_reconnection_fee
            && (float) ($customer->reconnection_fee_amount ?? 0) > 0) {
            InvoiceItem::query()->create([
                'invoice_id' => $invoice->id,
                'item_type' => 'reconnection_fee',
                'description' => 'Reconnection fee',
                'quantity' => 1,
                'unit_price' => round((float) $customer->reconnection_fee_amount, 2),
                'line_total' => 0,
                'sort_order' => $sort++,
            ]);
            $customer->forceFill(['pending_reconnection_fee' => false])->saveQuietly();
        }
    }

    private static function customerHasSetupFee(Customer $customer): bool
    {
        return InvoiceItem::query()
            ->where('item_type', 'setup_fee')
            ->whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id))
            ->exists();
    }

    private static function customerHasInstallationFee(Customer $customer): bool
    {
        return InvoiceItem::query()
            ->where('item_type', 'installation_fee')
            ->whereHas('invoice', fn ($q) => $q->where('customer_id', $customer->id))
            ->exists();
    }
}
