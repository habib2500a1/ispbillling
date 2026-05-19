<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;

final class PackageUpgradeApplicator
{
    public function __construct(
        private readonly PackageChangeQuoteService $quotes,
        private readonly ServiceExpiryExtensionService $expiry,
    ) {}

    public function applyWhenInvoicePaid(Invoice $invoice): void
    {
        if ($invoice->status !== 'paid') {
            return;
        }

        $item = $invoice->items()->where('item_type', 'package_upgrade')->first();
        if ($item === null) {
            return;
        }

        $packageId = (int) ($item->meta['target_package_id'] ?? 0);
        if ($packageId <= 0) {
            return;
        }

        $package = Package::query()->find($packageId);
        $customer = $invoice->customer;
        if (! $package instanceof Package || ! $customer instanceof Customer) {
            return;
        }

        if ((int) $customer->package_id === $packageId) {
            return;
        }

        $this->quotes->applyPackageChange($customer, $package);
        $this->expiry->extendForPaidCycle($customer->fresh() ?? $customer);
    }
}
