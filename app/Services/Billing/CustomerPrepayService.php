<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\CustomerBalanceDue;
use App\Support\PaymentRenewalPolicy;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

final class CustomerPrepayService
{
    public function __construct(
        private ServiceExpiryExtensionService $expiry,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('bill_payment.prepay_enabled', true);
    }

    public function maxMonths(): int
    {
        return max(1, min(36, (int) config('bill_payment.prepay_max_months', 12)));
    }

    /**
     * @return list<int>
     */
    public function quickMonthOptions(): array
    {
        $configured = config('bill_payment.prepay_quick_months', [1, 2, 3, 6, 12]);
        if (! is_array($configured)) {
            $configured = [1, 2, 3, 6, 12];
        }

        $options = collect($configured)
            ->map(fn ($value) => max(1, min($this->maxMonths(), (int) $value)))
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $options !== [] ? $options : [1, 2, 3, 6, 12];
    }

    public function monthlyRate(Customer $customer): ?float
    {
        $customer->loadMissing('package');

        if ($customer->package !== null) {
            $monthly = PackagePriceResolver::resolveBaseMonthlyPrice($customer->package, $customer);
            if ($monthly > 0) {
                return round($monthly, 2);
            }

            $cycle = PackagePriceResolver::resolveCyclePrice($customer->package, $customer);
            if ($cycle > 0) {
                return round($cycle, 2);
            }
        }

        return $this->fallbackMonthlyRate($customer);
    }

    private function fallbackMonthlyRate(Customer $customer): ?float
    {
        $meta = is_array($customer->meta) ? $customer->meta : [];
        $fromMeta = (float) ($meta['isp_digital_monthly_bill'] ?? 0);
        if ($fromMeta > 0) {
            return round($fromMeta, 2);
        }

        $fromInvoice = Invoice::query()
            ->withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('total', '>', 0)
            ->whereNotIn('status', ['void', 'cancelled'])
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->value('total');

        if ($fromInvoice !== null && (float) $fromInvoice > 0) {
            return round((float) $fromInvoice, 2);
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function quote(Customer $customer, int $months, bool $includeCurrentDue = true): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $months = max(1, min($this->maxMonths(), $months));
        $monthly = $this->monthlyRate($customer);
        if ($monthly === null) {
            return null;
        }

        $currentDue = $includeCurrentDue
            ? round((float) CustomerBalanceDue::amount($customer), 2)
            : 0.0;
        $prepayAmount = round($monthly * $months, 2);
        $totalAmount = round($currentDue + $prepayAmount, 2);

        $customer->loadMissing('package');

        return [
            'months' => $months,
            'monthly_rate' => $monthly,
            'current_due' => $currentDue,
            'prepay_amount' => $prepayAmount,
            'total_amount' => $totalAmount,
            'package_name' => $customer->package?->name,
            'cycle_days' => $customer->package ? $this->expiry->cycleDays($customer->package) : 30,
            'service_expires_at' => $customer->service_expires_at,
            'projected_expires_at' => $this->projectedExpiry($customer, $months),
            'quick_options' => $this->quickMonthOptions(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function assertQuote(Customer $customer, int $months): array
    {
        $quote = $this->quote($customer, $months);
        if ($quote === null) {
            throw ValidationException::withMessages([
                'months' => 'Advance payment is not available for this account.',
            ]);
        }

        $min = max((float) config('bill_payment.min_amount', 10), 0.01);
        if ($quote['total_amount'] < $min) {
            throw ValidationException::withMessages([
                'months' => 'Total amount is below the minimum payment limit.',
            ]);
        }

        return $quote;
    }

    public function projectedExpiry(Customer $customer, int $months): ?CarbonInterface
    {
        $customer->loadMissing('package');
        if ($customer->package === null || $months <= 0) {
            return null;
        }

        $base = PaymentRenewalPolicy::resolveBaseDate($customer);
        $days = $this->expiry->cycleDays($customer->package) * max(1, $months);

        return $base->copy()->addDays($days);
    }
}
