<?php

namespace App\Services\Sms;

use App\Models\CustomersInfo;
use Carbon\Carbon;

/**
 * Fills SMS template tokens in both catalog form ({CustomerName})
 * and legacy form ({CUSTOMER_NAME}).
 */
final class SmsPlaceholderRenderer
{
    public function render(string $template, array $data = [], ?CustomersInfo $customer = null): string
    {
        $values = $this->values($data, $customer);

        return (string) preg_replace_callback('/\{([A-Za-z0-9_]+)\}/', function (array $m) use ($values) {
            $key = $this->normalize($m[1]);

            return array_key_exists($key, $values) ? (string) $values[$key] : $m[0];
        }, $template);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    private function values(array $data, ?CustomersInfo $customer): array
    {
        $customer ??= $data['customer'] ?? null;
        if (! $customer instanceof CustomersInfo) {
            $uid = $data['customer_id'] ?? $data['customer_unique_id'] ?? null;
            $customer = $uid
                ? CustomersInfo::query()->with(['billing', 'pppUser', 'package'])->where('customer_unique_id', $uid)->first()
                : null;
        } else {
            $customer->loadMissing(['billing', 'pppUser', 'package']);
        }

        $name = (string) ($data['customer_name'] ?? $customer?->customer_name ?? '');
        $uid = (string) ($data['customer_id'] ?? $data['customer_unique_id'] ?? $customer?->customer_unique_id ?? '');
        $username = (string) ($data['ip_or_user_name'] ?? $data['username'] ?? $customer?->pppUser?->username ?? '');
        $password = (string) ($data['password'] ?? $customer?->pppUser?->password ?? '');
        $package = (string) ($data['package'] ?? $customer?->package?->package ?? '');
        $paid = $data['collection_amount'] ?? $data['paid_amount'] ?? $data['amount'] ?? '';
        $dueRaw = $data['due_amount'] ?? $data['due'] ?? $customer?->billing?->due_amount ?? 0;
        $due = is_numeric($dueRaw) ? max(0, (float) $dueRaw) : $dueRaw;
        $bill = $data['bill_amount'] ?? $data['total_amount'] ?? $customer?->billing?->total_amount ?? '';
        $rent = $data['monthly_rent'] ?? $customer?->billing?->monthly_rent ?? '';
        $expire = $data['last_day_of_pay_bill'] ?? $data['billing_last_date'] ?? null;
        if (! $expire && $customer?->billing?->auto_disable_date) {
            $expire = Carbon::parse($customer->billing->auto_disable_date)->format('d-M-Y');
        }
        $month = (string) ($data['month'] ?? now()->format('F Y'));
        $invoice = (string) ($data['invoice_number'] ?? $data['invoice_no'] ?? '');
        $company = (string) ($data['company_name'] ?? siteUrlSettings('site_name') ?: config('app.name'));
        $helpline = (string) ($data['company_mobile'] ?? siteUrlSettings('site_phone') ?? '');
        $message = (string) ($data['message'] ?? '');
        $code = (string) ($data['code'] ?? $data['verification_code'] ?? '');
        $problem = (string) ($data['problem'] ?? '');

        $pairs = [
            'customername' => $name,
            'name' => $name,
            'clientid' => $uid,
            'customerid' => $uid,
            'id' => $uid,
            'username' => $username,
            'iporusernameorid' => $username !== '' ? $username : $uid,
            'pppoeusername' => $username,
            'password' => $password,
            'package' => $package,
            'paidamount' => $paid,
            'amount' => $paid,
            'collectionamount' => $paid,
            'due' => $due,
            'dueamount' => $due,
            'balance' => $due,
            'billamount' => $bill,
            'monthlybillamount' => $rent,
            'monthlyrent' => $rent,
            'month' => $month,
            'invoicenumber' => $invoice,
            'invoiceno' => $invoice,
            'billinglastdate' => $expire ?? '',
            'lastdayofpaybill' => $expire ?? '',
            'autotemporaryday' => $expire ?? '',
            'companyname' => $company,
            'companymobile' => $helpline,
            'companyphone' => $helpline,
            'helpline' => $helpline,
            'message' => $message,
            'verificationcode' => $code,
            'code' => $code,
            'problem' => $problem,
            'minutes' => (string) ($data['minutes'] ?? 10),
        ];

        $moneyKeys = ['paidamount', 'amount', 'collectionamount', 'due', 'dueamount', 'balance', 'billamount', 'monthlybillamount', 'monthlyrent'];
        $out = [];
        foreach ($pairs as $key => $value) {
            if (in_array($key, $moneyKeys, true) && is_numeric($value)) {
                $out[$key] = number_format((float) $value, 2, '.', '');
            } else {
                $out[$key] = (string) ($value ?? '');
            }
        }

        return $out;
    }

    private function normalize(string $token): string
    {
        return strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $token));
    }
}
