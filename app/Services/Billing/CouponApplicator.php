<?php

namespace App\Services\Billing;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Invoice;
use Illuminate\Validation\ValidationException;

final class CouponApplicator
{
    /**
     * @throws ValidationException
     */
    public static function apply(Invoice $invoice, string $code, bool $incrementUsage = true): void
    {
        $code = strtoupper(trim($code));
        if ($code === '') {
            throw ValidationException::withMessages(['coupon_code' => 'Coupon code is required.']);
        }

        $invoice->load(['customer', 'items']);
        $customer = $invoice->customer;
        if ($customer === null) {
            throw ValidationException::withMessages(['coupon_code' => 'Invoice has no subscriber.']);
        }

        $coupon = Coupon::query()
            ->where('tenant_id', $customer->tenant_id)
            ->where('code', $code)
            ->first();

        if ($coupon === null) {
            throw ValidationException::withMessages(['coupon_code' => 'Invalid coupon code.']);
        }

        if (! $coupon->isValidAt(now())) {
            throw ValidationException::withMessages(['coupon_code' => 'This coupon is not valid right now.']);
        }

        $subtotal = (float) $invoice->items->sum('line_total');
        $min = (float) ($coupon->min_invoice_amount ?? 0);
        if ($min > 0 && $subtotal < $min) {
            throw ValidationException::withMessages([
                'coupon_code' => 'Minimum invoice amount for this coupon is '.number_format($min, 2).' BDT.',
            ]);
        }

        $discount = static::computeDiscount($coupon, $subtotal, $customer);

        $invoice->forceFill([
            'coupon_id' => $coupon->id,
            'coupon_discount_amount' => $discount,
        ])->saveQuietly();

        if ($incrementUsage) {
            $coupon->increment('uses_count');
        }

        InvoiceCalculator::recalculate($invoice->fresh());
    }

    public static function remove(Invoice $invoice): void
    {
        $invoice->forceFill([
            'coupon_id' => null,
            'coupon_discount_amount' => 0,
        ])->saveQuietly();

        InvoiceCalculator::recalculate($invoice->fresh());
    }

    public static function computeDiscount(Coupon $coupon, float $subtotal, Customer $customer): float
    {
        $value = (float) $coupon->value;

        return match ($coupon->discount_type) {
            Coupon::TYPE_PERCENT => round($subtotal * min(100, max(0, $value)) / 100, 2),
            Coupon::TYPE_FIXED_AMOUNT => round(min($subtotal, max(0, $value)), 2),
            Coupon::TYPE_FIRST_MONTH_PERCENT => static::firstMonthPercentDiscount($customer, $subtotal, $value),
            default => 0.0,
        };
    }

    private static function firstMonthPercentDiscount(Customer $customer, float $subtotal, float $percent): float
    {
        $alreadyUsed = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('coupon_discount_amount', '>', 0)
            ->exists();

        if ($alreadyUsed) {
            return 0.0;
        }

        return round($subtotal * min(100, max(0, $percent)) / 100, 2);
    }
}
