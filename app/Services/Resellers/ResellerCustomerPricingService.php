<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Package;
use App\Models\Reseller;
use App\Support\ResellerNewCustomerChargeMode;
use Carbon\Carbon;

/**
 * Reseller-facing retail (sell) vs wholesale (buy) pricing for a subscriber.
 */
final class ResellerCustomerPricingService
{
    public function __construct(
        private readonly ResellerPackageCatalogService $catalog,
    ) {}

    public function hasRetailOverride(Customer $customer): bool
    {
        $v = data_get($customer->meta, 'reseller_retail_monthly_bdt');

        return $v !== null && $v !== '' && is_numeric($v);
    }

    public function retailOverride(Customer $customer): ?float
    {
        if (! $this->hasRetailOverride($customer)) {
            return null;
        }

        return max(0, round((float) data_get($customer->meta, 'reseller_retail_monthly_bdt'), 2));
    }

    /**
     * Effective monthly bill before cycle scaling (matches invoice package line base).
     */
    public function effectiveRetailMonthly(Customer $customer, ?Package $package = null): float
    {
        $package ??= $customer->package;
        if ($package === null) {
            return 0.0;
        }

        return $this->catalog->customerBillPriceFor($package, $customer);
    }

    /**
     * Package list / zone price without per-customer override or discount.
     */
    public function catalogRetailMonthly(Customer $customer, ?Package $package = null): float
    {
        $package ??= $customer->package;
        if ($package === null) {
            return 0.0;
        }

        $clone = clone $customer;
        $meta = is_array($clone->meta) ? $clone->meta : [];
        unset($meta['reseller_retail_monthly_bdt'], $meta['monthly_discount_bdt']);
        $clone->meta = $meta;

        return $this->catalog->customerBillPriceFor($package, $clone);
    }

    public function wholesaleMonthly(Reseller $reseller, Customer $customer, ?Package $package = null): ?float
    {
        $package ??= $customer->package;
        if ($package === null) {
            return null;
        }

        return $this->catalog->wholesalePriceFor($reseller, $package);
    }

    public function marginMonthly(Reseller $reseller, Customer $customer): ?float
    {
        $buy = $this->wholesaleMonthly($reseller, $customer);
        if ($buy === null) {
            return null;
        }

        return max(0, round($this->effectiveRetailMonthly($customer) - $buy, 2));
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshot(Reseller $reseller, Customer $customer): array
    {
        $customer->loadMissing('package:id,name,price_monthly,billing_cycle_type,billing_cycle_days');
        $package = $customer->package;

        $retail = $this->effectiveRetailMonthly($customer, $package);
        $catalogRetail = $this->catalogRetailMonthly($customer, $package);
        $buy = $this->wholesaleMonthly($reseller, $customer, $package);
        $margin = $buy !== null ? max(0, round($retail - $buy, 2)) : null;
        $marginPct = ($buy !== null && $retail > 0)
            ? round(($margin / $retail) * 100, 1)
            : null;

        $estimate = $package
            ? app(ResellerInvoiceSplitCalculator::class)->estimateForCustomer($customer, Carbon::today())
            : ['retail' => 0.0, 'wholesale' => 0.0, 'margin' => 0.0];

        $meta = is_array($customer->meta) ? $customer->meta : [];
        $chargeMode = $meta['new_customer_charge_mode'] ?? $reseller->new_customer_charge_mode;
        $chargeLabels = ResellerNewCustomerChargeMode::labels();

        return [
            'package_name' => $package?->name,
            'list_price_monthly' => $package ? (float) $package->price_monthly : null,
            'catalog_retail_monthly' => $catalogRetail,
            'retail_monthly' => $retail,
            'retail_override' => $this->retailOverride($customer),
            'monthly_discount' => max(0, (float) ($meta['monthly_discount_bdt'] ?? 0)),
            'discount_note' => trim((string) ($meta['discount_note'] ?? '')),
            'wholesale_monthly' => $buy,
            'margin_monthly' => $margin,
            'margin_percent' => $marginPct,
            'estimated_cycle_retail' => $estimate['retail'],
            'estimated_cycle_wholesale' => $estimate['wholesale'],
            'estimated_cycle_margin' => $estimate['margin'],
            'onu_rent' => max(0, (float) ($meta['onu_rent'] ?? 0)),
            'router_rent' => max(0, (float) ($meta['router_rent'] ?? 0)),
            'installation_charge' => max(0, (float) ($meta['installation_charge'] ?? 0)),
            'charge_mode' => $chargeMode,
            'charge_mode_label' => $chargeLabels[$chargeMode] ?? (string) $chargeMode,
            'billing_day' => (int) ($customer->billing_day ?? 1),
            'joined_at' => $customer->joined_at?->format('d M Y'),
            'service_expires_at' => $customer->service_expires_at?->format('d M Y'),
            'total_monthly_addons' => round(
                max(0, (float) ($meta['onu_rent'] ?? 0))
                + max(0, (float) ($meta['router_rent'] ?? 0)),
                2,
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function applyPricingMeta(Customer $customer, array $validated, bool $merge = true): void
    {
        $meta = $merge && is_array($customer->meta) ? $customer->meta : [];

        foreach ([
            'reseller_retail_monthly_bdt',
            'monthly_discount_bdt',
            'discount_note',
            'onu_rent',
            'router_rent',
            'installation_charge',
        ] as $key) {
            if (! array_key_exists($key, $validated)) {
                continue;
            }
            $value = $validated[$key];
            if ($key === 'discount_note') {
                if (filled($value)) {
                    $meta[$key] = trim((string) $value);
                } else {
                    unset($meta[$key]);
                }
                continue;
            }
            if ($value === null || $value === '') {
                unset($meta[$key]);
                continue;
            }
            $num = round((float) $value, 2);
            if ($key === 'reseller_retail_monthly_bdt' && $num <= 0) {
                unset($meta[$key]);
                continue;
            }
            if (in_array($key, ['monthly_discount_bdt', 'onu_rent', 'router_rent', 'installation_charge'], true) && $num <= 0) {
                unset($meta[$key]);
                continue;
            }
            $meta[$key] = $num;
        }

        if (array_key_exists('new_customer_charge_mode', $validated) && filled($validated['new_customer_charge_mode'])) {
            $meta['new_customer_charge_mode'] = (string) $validated['new_customer_charge_mode'];
        }

        $customer->meta = $meta;
    }
}
