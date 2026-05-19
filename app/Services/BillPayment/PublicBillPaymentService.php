<?php

namespace App\Services\BillPayment;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\CustomerStatus;
use Illuminate\Support\Collection;

class PublicBillPaymentService
{
    public function findByClientCode(string $code): ?Customer
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        return Customer::query()
            ->withoutGlobalScopes()
            ->with(['package:id,name,price_monthly'])
            ->where(function ($q) use ($code): void {
                $q->where('customer_code', $code);
                $digits = preg_replace('/\D+/', '', $code) ?? '';
                if ($digits !== '') {
                    $q->orWhere('phone', $digits)->orWhere('phone', $code);
                }
            })
            ->first();
    }

    /**
     * @return Collection<int, Invoice>
     */
    public function payableInvoices(Customer $customer): Collection
    {
        return Invoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'draft'])
            ->whereRaw('(total - amount_paid) > 0')
            ->orderBy('due_date')
            ->get();
    }

    public function totalDue(Customer $customer): float
    {
        return round((float) $this->payableInvoices($customer)->sum(fn (Invoice $i) => $i->balanceDue()), 2);
    }

    /**
     * @return array<string, mixed>
     */
    public function customerSummary(Customer $customer): array
    {
        $invoices = $this->payableInvoices($customer);
        $totalDue = round((float) $invoices->sum(fn (Invoice $i) => $i->balanceDue()), 2);

        $lastPayment = Payment::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $customer->tenant_id)
            ->where('customer_id', $customer->id)
            ->where('status', 'completed')
            ->latest('paid_at')
            ->first();

        return [
            'customer' => $customer,
            'package_name' => $customer->package?->name,
            'status' => $customer->status,
            'status_label' => CustomerStatus::label($customer->status),
            'can_pay' => $totalDue > 0 && ! in_array($customer->status, [CustomerStatus::TERMINATED], true),
            'total_due' => $totalDue,
            'invoice_count' => $invoices->count(),
            'invoices' => $invoices,
            'wallet_balance' => (float) $customer->account_balance,
            'last_payment' => $lastPayment,
        ];
    }

    public function canAcceptPayments(Customer $customer): bool
    {
        if ($customer->status === CustomerStatus::TERMINATED) {
            return false;
        }

        return $this->totalDue($customer) > 0;
    }
}
