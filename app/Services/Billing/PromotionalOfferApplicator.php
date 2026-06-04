<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\PromotionalOffer;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Auto-applies active promotional offers (Sheba-Fi style) to invoices by package + date.
 */
final class PromotionalOfferApplicator
{
    public static function enabled(): bool
    {
        return (bool) config('billing.auto_apply_promotional_offers', true);
    }

    /**
     * Best active offer for subscriber package (highest discount on given amount).
     */
    public static function bestForCustomer(
        Customer $customer,
        ?Package $package = null,
        ?CarbonInterface $at = null,
        ?float $forSubtotal = null,
    ): ?PromotionalOffer {
        if (! static::enabled()) {
            return null;
        }

        $package ??= static::resolvePackage($customer);
        $packageId = $package?->id;
        $at ??= now();

        /** @var Collection<int, PromotionalOffer> $candidates */
        $candidates = PromotionalOffer::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('is_active', true)
            ->get()
            ->filter(fn (PromotionalOffer $offer): bool => $offer->isValidAt($at)
                && $offer->appliesToPackage($packageId));

        if ($candidates->isEmpty()) {
            return null;
        }

        $amount = $forSubtotal !== null ? max(0, round($forSubtotal, 2)) : 0.0;

        return $candidates->sortByDesc(
            fn (PromotionalOffer $offer): float => static::computeDiscount($offer, $amount > 0 ? $amount : 1.0),
        )->first();
    }

    public static function computeDiscount(PromotionalOffer $offer, float $subtotal): float
    {
        $subtotal = max(0, round($subtotal, 2));
        if ($subtotal <= 0) {
            return 0.0;
        }

        $value = (float) $offer->discount_value;

        return match ($offer->discount_type) {
            PromotionalOffer::TYPE_PERCENT => round($subtotal * min(100, max(0, $value)) / 100, 2),
            PromotionalOffer::TYPE_FIXED => round(min($subtotal, max(0, $value)), 2),
            default => 0.0,
        };
    }

    /**
     * Apply best offer to invoice. Skips when a coupon is already linked (no stack).
     */
    public static function applyBestToInvoice(
        Invoice $invoice,
        ?Package $forPackage = null,
        bool $incrementRedemption = true,
    ): bool {
        if (! static::enabled()) {
            return false;
        }

        $invoice->load(['customer.package', 'items']);

        if ($invoice->coupon_id !== null || (float) ($invoice->coupon_discount_amount ?? 0) > 0.009) {
            return false;
        }

        $customer = $invoice->customer;
        if ($customer === null) {
            return false;
        }

        $subtotal = (float) $invoice->items->sum('line_total');
        $package = $forPackage ?? static::resolvePackage($customer);
        $offer = static::bestForCustomer($customer, $package, null, $subtotal);
        if ($offer === null) {
            static::clear($invoice);

            return false;
        }

        $discount = static::computeDiscount($offer, $subtotal);
        if ($discount <= 0) {
            static::clear($invoice);

            return false;
        }

        $invoice->forceFill([
            'promotional_offer_id' => $offer->id,
            'promotional_offer_discount_amount' => $discount,
        ])->saveQuietly();

        if ($incrementRedemption) {
            $offer->increment('redemptions_count');
        }

        InvoiceCalculator::recalculate($invoice->fresh());

        return true;
    }

    public static function clear(Invoice $invoice): void
    {
        if ($invoice->promotional_offer_id === null && (float) ($invoice->promotional_offer_discount_amount ?? 0) <= 0) {
            return;
        }

        $invoice->forceFill([
            'promotional_offer_id' => null,
            'promotional_offer_discount_amount' => 0,
        ])->saveQuietly();

        InvoiceCalculator::recalculate($invoice->fresh());
    }

    /**
     * Preview discount for package change quote (does not mutate invoices).
     */
    private static function resolvePackage(Customer $customer): ?Package
    {
        if ($customer->package_id === null) {
            return null;
        }

        if ($customer->relationLoaded('package') && $customer->getRelation('package') instanceof Package) {
            return $customer->getRelation('package');
        }

        return Package::query()->withoutGlobalScopes()->find($customer->package_id);
    }

    public static function previewDiscount(Customer $customer, Package $package, float $taxableAmount): float
    {
        $amount = max(0, round($taxableAmount, 2));
        $offer = static::bestForCustomer($customer, $package, null, $amount);

        return $offer !== null ? static::computeDiscount($offer, $amount) : 0.0;
    }
}
