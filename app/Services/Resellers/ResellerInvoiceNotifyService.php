<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentLink;
use App\Models\Reseller;
use App\Services\BillPayment\PaymentLinkService;
use App\Services\Notifications\NotificationDispatcher;
use App\Services\Reseller\ResellerIntegrationSettings;
use App\Support\CompanyBranding;
use App\Support\NotificationChannel;
use Illuminate\Validation\ValidationException;

final class ResellerInvoiceNotifyService
{
    public function __construct(
        private readonly PaymentLinkService $paymentLinks,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    /**
     * @return array{sms: bool, email: bool}
     */
    public function availableChannels(Customer $customer): array
    {
        return [
            'sms' => filled($customer->phone) && $this->smsEnabled($customer),
            'email' => filled($customer->email) && $this->emailEnabled(),
        ];
    }

    /**
     * @param  list<string>  $channels
     * @return array{sms: bool, email: bool, payment_url: ?string, whatsapp_url: ?string}
     */
    public function send(Invoice $invoice, Reseller $reseller, array $channels, bool $includePaymentLink = true): array
    {
        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if ($customer === null) {
            throw ValidationException::withMessages(['invoice' => 'Invoice has no subscriber.']);
        }

        $channels = array_values(array_unique(array_filter($channels, fn (string $c): bool => in_array($c, ['sms', 'email'], true))));
        if ($channels === []) {
            throw ValidationException::withMessages(['channels' => 'Select SMS and/or email.']);
        }

        $available = $this->availableChannels($customer);
        foreach ($channels as $channel) {
            if (! ($available[$channel] ?? false)) {
                throw ValidationException::withMessages([
                    $channel => ucfirst($channel).' is not available for this subscriber.',
                ]);
            }
        }

        $balance = round(max(0, (float) $invoice->total - (float) $invoice->amount_paid), 2);
        $paymentUrl = null;
        $paymentLink = null;

        if ($includePaymentLink && $balance > 0) {
            $paymentLink = $this->paymentLinks->create(
                $customer,
                PaymentLink::PURPOSE_INVOICE,
                $invoice,
                $balance,
                null,
                'reseller_portal:invoice_send',
            );
            $paymentUrl = $paymentLink->publicUrl();
        }

        $company = $this->senderName($reseller);
        $pdfUrl = route('portal.invoices.pdf', $invoice);
        $sent = ['sms' => false, 'email' => false];

        if (in_array('sms', $channels, true)) {
            $this->dispatcher->send(
                (int) $invoice->tenant_id,
                (int) $customer->id,
                'invoice_sent',
                NotificationChannel::SMS,
                (string) $customer->phone,
                $this->smsMessage($company, $invoice, $balance, $paymentUrl),
                ['subject' => 'Invoice '.$invoice->invoice_number],
            );
            $sent['sms'] = true;
            $paymentLink?->update(['sms_sent_at' => now()]);
        }

        if (in_array('email', $channels, true)) {
            $this->dispatcher->send(
                (int) $invoice->tenant_id,
                (int) $customer->id,
                'invoice_sent',
                NotificationChannel::EMAIL,
                (string) $customer->email,
                $this->emailMessage($company, $invoice, $customer, $balance, $paymentUrl, $pdfUrl),
                ['subject' => 'Invoice '.$invoice->invoice_number.' — '.$company],
            );
            $sent['email'] = true;
        }

        return array_merge($sent, [
            'payment_url' => $paymentUrl,
            'whatsapp_url' => $paymentLink !== null
                ? $this->paymentLinks->whatsAppShareUrl($paymentLink, $customer)
                : null,
        ]);
    }

    private function senderName(Reseller $reseller): string
    {
        if ($reseller->white_label_enabled && filled($reseller->brand_name)) {
            return (string) $reseller->brand_name;
        }

        return CompanyBranding::name();
    }

    private function smsMessage(string $company, Invoice $invoice, float $balance, ?string $paymentUrl): string
    {
        $lines = [
            "{$company}: Invoice {$invoice->invoice_number}.",
            'Due: '.number_format($balance, 2).' BDT',
        ];

        if ($invoice->due_date !== null) {
            $lines[] = 'Pay by: '.$invoice->due_date->format('d M Y').'.';
        }

        if ($paymentUrl !== null) {
            $lines[] = 'Pay: '.$paymentUrl;
        }

        return implode(' ', $lines);
    }

    private function emailMessage(
        string $company,
        Invoice $invoice,
        Customer $customer,
        float $balance,
        ?string $paymentUrl,
        string $pdfUrl,
    ): string {
        $parts = [
            "Dear {$customer->name},",
            '',
            "Your invoice {$invoice->invoice_number} is ready.",
            'Amount due: '.number_format($balance, 2).' BDT',
        ];

        if ($invoice->due_date !== null) {
            $parts[] = 'Due date: '.$invoice->due_date->format('d M Y');
        }

        $parts[] = '';
        $parts[] = 'Download PDF: '.$pdfUrl;

        if ($paymentUrl !== null) {
            $parts[] = 'Pay online: '.$paymentUrl;
        }

        $parts[] = '';
        $parts[] = '— '.$company;

        return implode("\n", $parts);
    }

    private function smsEnabled(Customer $customer): bool
    {
        if ((bool) config('notifications.sms.enabled', false)) {
            return true;
        }

        if (! $customer->reseller_id) {
            return false;
        }

        $reseller = Reseller::query()->withoutGlobalScopes()->find($customer->reseller_id);
        if ($reseller === null || ! $reseller->own_integrations_enabled) {
            return false;
        }

        return ResellerIntegrationSettings::summary($reseller)['sms_active'];
    }

    private function emailEnabled(): bool
    {
        return (bool) config('notifications.email.enabled', true);
    }
}
