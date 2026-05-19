<?php

namespace App\Services\BillPayment;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentLink;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationChannel;

class PaymentLinkService
{
    public function create(
        Customer $customer,
        string $purpose = PaymentLink::PURPOSE_INVOICE,
        ?Invoice $invoice = null,
        ?float $amount = null,
        ?int $createdBy = null,
        ?string $sourceEvent = null,
    ): PaymentLink {
        $days = max(1, (int) config('bill_payment.link_ttl_days', 7));

        return PaymentLink::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'invoice_id' => $invoice?->id,
            'created_by' => $createdBy,
            'token' => PaymentLink::generateToken(),
            'purpose' => $purpose,
            'source_event' => $sourceEvent,
            'amount' => $amount !== null ? round($amount, 2) : null,
            'expires_at' => now()->addDays($days),
        ]);
    }

    public function resolve(string $token): ?PaymentLink
    {
        $link = PaymentLink::query()
            ->withoutGlobalScopes()
            ->with(['customer', 'invoice'])
            ->where('token', $token)
            ->first();

        if ($link === null || ! $link->isValid()) {
            return null;
        }

        $link->increment('access_count');
        if ($link->first_clicked_at === null) {
            $link->forceFill(['first_clicked_at' => now()])->saveQuietly();
        }

        return $link;
    }

    public function whatsAppShareUrl(PaymentLink $link, ?Customer $customer = null): ?string
    {
        $customer ??= $link->customer;
        if ($customer === null || blank($customer->phone)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $customer->phone) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '880'.substr($digits, 1);
        } elseif (! str_starts_with($digits, '880')) {
            $digits = '880'.$digits;
        }

        $company = (string) config('isp.company_name', 'ISP');
        $url = $link->publicUrl();
        $text = rawurlencode("{$company}: Pay your bill online. Client {$customer->customer_code}. {$url}");

        return 'https://wa.me/'.$digits.'?text='.$text;
    }

    public function markConverted(?int $invoiceId, int $paymentId): void
    {
        if ($invoiceId === null) {
            return;
        }

        $link = PaymentLink::query()
            ->where('invoice_id', $invoiceId)
            ->whereNull('converted_payment_id')
            ->latest('id')
            ->first();

        if ($link !== null) {
            $link->forceFill([
                'converted_payment_id' => $paymentId,
                'used_at' => now(),
            ])->saveQuietly();
        }
    }

    public function sendSms(PaymentLink $link): bool
    {
        $customer = $link->customer;
        if ($customer === null || blank($customer->phone)) {
            return false;
        }

        $url = $link->publicUrl();
        $company = (string) config('isp.company_name', 'ISP');
        $amountLine = $link->amount !== null
            ? ' Amount: '.number_format((float) $link->amount, 2).' BDT.'
            : '';

        $message = "{$company}: Pay your bill online. Client {$customer->customer_code}.{$amountLine} Link: {$url}";

        app(NotificationDispatcher::class)->send(
            (int) $link->tenant_id,
            (int) $customer->id,
            'payment_link',
            NotificationChannel::SMS,
            $customer->phone,
            $message,
            ['subject' => 'Payment link'],
        );

        $link->update(['sms_sent_at' => now()]);

        return true;
    }

    public function markUsed(PaymentLink $link): void
    {
        if ($link->used_at === null) {
            $link->update(['used_at' => now()]);
        }
    }
}
