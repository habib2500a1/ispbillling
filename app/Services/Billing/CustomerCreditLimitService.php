<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;

final class CustomerCreditLimitService
{
    public function openBalance(Customer $customer): float
    {
        return round((float) Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['paid', 'void', 'cancelled', 'draft'])
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')
            ->value('due'), 2);
    }

    public function creditLimit(Customer $customer): ?float
    {
        $limit = $customer->credit_limit;
        if ($limit === null || (float) $limit <= 0) {
            return null;
        }

        return round((float) $limit, 2);
    }

    public function isOverCreditLimit(Customer $customer, float $additionalAmount = 0): bool
    {
        if (! config('billing.credit_limit_enforced', true)) {
            return false;
        }

        $limit = $this->creditLimit($customer);
        if ($limit === null) {
            return false;
        }

        return ($this->openBalance($customer) + max(0, $additionalAmount)) > $limit;
    }

    public function canGenerateInvoice(Customer $customer, float $estimatedAmount = 0): bool
    {
        return ! $this->isOverCreditLimit($customer, $estimatedAmount);
    }
}
