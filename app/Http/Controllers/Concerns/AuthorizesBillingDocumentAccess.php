<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\User;
use App\Support\Rbac\StaffCapability;
use Illuminate\Support\Facades\Auth;

trait AuthorizesBillingDocumentAccess
{
    protected function resolveStaffUser(): ?User
    {
        $web = Auth::guard('web')->user();
        if ($web instanceof User) {
            return $web;
        }

        $sanctum = Auth::guard('sanctum')->user();
        if ($sanctum instanceof User) {
            return $sanctum;
        }

        return null;
    }

    protected function staffMayAccessBillingDocuments(?User $staff): bool
    {
        if ($staff === null) {
            return false;
        }

        $cap = StaffCapability::for($staff);

        return $cap->isTenantAdmin()
            || $cap->canBilling()
            || $cap->canPayments()
            || $cap->canCollect()
            || $cap->canReports();
    }

    protected function authorizeInvoicePdf(Invoice $invoice): void
    {
        $staff = $this->resolveStaffUser();
        if ($staff !== null && $this->staffMayAccessBillingDocuments($staff)) {
            return;
        }

        $sanctumReseller = Auth::guard('sanctum')->user();
        $sessionReseller = Auth::guard('reseller')->user();
        $reseller = $sanctumReseller instanceof Reseller
            ? $sanctumReseller
            : ($sessionReseller instanceof Reseller ? $sessionReseller : null);

        if ($reseller instanceof Reseller) {
            $invoice->loadMissing('customer');
            abort_unless(
                $invoice->customer !== null
                    && (int) $invoice->customer->reseller_id === (int) $reseller->getAuthIdentifier(),
                403,
            );

            return;
        }

        $customer = Auth::guard('customer')->user();
        if ($customer instanceof Customer) {
            abort_unless((int) $invoice->customer_id === (int) $customer->getAuthIdentifier(), 403);

            return;
        }

        if (
            session('bill_pay.verified')
            && session('bill_pay.customer_id')
            && (int) session('bill_pay.customer_id') === (int) $invoice->customer_id
        ) {
            return;
        }

        abort(401);
    }

    protected function authorizePaymentReceipt(Payment $payment): void
    {
        $staff = $this->resolveStaffUser();
        if ($staff !== null && $this->staffMayAccessBillingDocuments($staff)) {
            return;
        }

        $sanctumReseller = Auth::guard('sanctum')->user();
        $sessionReseller = Auth::guard('reseller')->user();
        $reseller = $sanctumReseller instanceof Reseller
            ? $sanctumReseller
            : ($sessionReseller instanceof Reseller ? $sessionReseller : null);

        if ($reseller instanceof Reseller) {
            $payment->loadMissing('customer');
            abort_unless(
                $payment->customer !== null
                    && (int) $payment->customer->reseller_id === (int) $reseller->getAuthIdentifier(),
                403,
            );

            return;
        }

        $customer = Auth::guard('customer')->user();
        if ($customer instanceof Customer) {
            abort_unless((int) $payment->customer_id === (int) $customer->getAuthIdentifier(), 403);

            return;
        }

        abort(401);
    }
}
