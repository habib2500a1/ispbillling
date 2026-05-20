<?php

namespace App\Services\Sms;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SupportTicket;
use App\Support\CustomerStatus;

final class SmsTemplateVariableBuilder
{
    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            'CompanyName' => (string) config('isp.company_name', 'ISP'),
            'CompanyMobile' => (string) config('isp.company_phone', ''),
            'BaseSiteURL' => (string) config('app.url', ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function forCustomer(Customer $customer): array
    {
        $package = $customer->package;

        return array_merge(self::defaults(), [
            'CustomerName' => (string) $customer->name,
            'ClientID' => (string) $customer->customer_code,
            'ClientCode' => (string) $customer->customer_code,
            'UserName' => (string) ($customer->ppp_username ?? $customer->customer_code),
            'LoginUserName' => (string) ($customer->ppp_username ?? $customer->customer_code),
            'Password' => '****',
            'LoginPassword' => '****',
            'Package' => (string) ($package?->name ?? '—'),
            'MonthlyBillAmount' => number_format((float) ($package?->price_monthly ?? 0), 2),
            'CustomerNumber' => (string) ($customer->phone ?? ''),
            'Zone' => (string) ($customer->zone?->name ?? $customer->area?->name ?? '—'),
            'Address' => (string) ($customer->address ?? '—'),
            'Due' => number_format($customer->openInvoiceBalance(), 2),
            'BillingLastDate' => (string) ($customer->billing_cycle_day ?? '—'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function forPayment(Payment $payment): array
    {
        $customer = $payment->customer;
        $base = $customer ? self::forCustomer($customer) : self::defaults();

        $walletCredit = (float) ($payment->meta['wallet_credit'] ?? 0);

        return array_merge($base, [
            'PaidAmount' => number_format((float) $payment->amount, 2),
            'amount' => number_format((float) $payment->amount, 2),
            'invoice_number' => (string) ($payment->invoice?->invoice_number ?? '—'),
            'receipt_number' => (string) ($payment->receipt_number ?? '—'),
            'Due' => $customer ? number_format($customer->openInvoiceBalance(), 2) : '0.00',
            'payment_kind' => \App\Services\Billing\CollectionPaymentClassifier::isAdvancePayment($payment)
                ? 'Advance (অগ্রিম)'
                : 'Bill payment',
            'wallet_credit' => $walletCredit > 0 ? number_format($walletCredit, 2) : '0.00',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function forTicket(SupportTicket $ticket): array
    {
        $customer = $ticket->customer;
        $base = $customer ? self::forCustomer($customer) : self::defaults();

        return array_merge($base, [
            'Problem' => (string) ($ticket->subject ?? $ticket->description ?? '—'),
            'UserName' => (string) ($customer?->ppp_username ?? $customer?->customer_code ?? '—'),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public static function forOtp(string $code, int $minutes = 10): array
    {
        return array_merge(self::defaults(), [
            'VerificationCode' => $code,
            'code' => $code,
            'minutes' => (string) $minutes,
        ]);
    }

    public static function statusEventKey(Customer $customer, string $from, string $to): ?string
    {
        $toNorm = CustomerStatus::normalize($to);
        if (in_array($toNorm, [CustomerStatus::SUSPENDED, CustomerStatus::TERMINATED, CustomerStatus::EXPIRED], true)) {
            return 'client_disable';
        }
        if ($toNorm === CustomerStatus::ACTIVE && ! in_array(CustomerStatus::normalize($from), [CustomerStatus::ACTIVE], true)) {
            return 'client_enable';
        }

        return null;
    }
}
