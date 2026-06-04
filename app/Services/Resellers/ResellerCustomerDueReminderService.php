<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentLink;
use App\Models\Reseller;
use App\Services\BillPayment\PaymentLinkService;
use App\Services\Notifications\NotificationDispatcher;
use App\Support\NotificationEvent;
use Illuminate\Support\Facades\Cache;

final class ResellerCustomerDueReminderService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly PaymentLinkService $paymentLinks,
    ) {}

    public function sendForInvoice(Invoice $invoice, Reseller $reseller): bool
    {
        if (! config('reseller_billing.due_reminders.reseller_portal_enabled', true)) {
            return false;
        }

        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if ($customer === null || (int) $customer->reseller_id !== (int) $reseller->id) {
            return false;
        }

        $balance = max(0, round((float) $invoice->total - (float) $invoice->amount_paid, 2));
        if ($balance <= 0) {
            return false;
        }

        $cacheKey = "reseller-reminder:{$reseller->id}:{$invoice->id}";
        if (Cache::has($cacheKey)) {
            return false;
        }

        $this->dispatcher->notifyCustomer($customer, NotificationEvent::INVOICE_DUE, [
            'invoice_number' => $invoice->invoice_number ?? '—',
            'balance' => number_format($balance, 2),
            'due_date' => $invoice->due_date?->toFormattedDateString() ?? '—',
            'payment_url' => $this->paymentUrl($customer, $invoice, $balance),
            'reseller_name' => $reseller->displayName(),
        ], [
            'subject' => 'Bill reminder — '.($invoice->invoice_number ?? ''),
            'bypass_event_gate' => true,
        ]);

        $hours = max(1, (int) config('reseller_billing.due_reminders.cooldown_hours', 24));
        Cache::put($cacheKey, true, now()->addHours($hours));

        app(ResellerPortalActivityLogger::class)->log($reseller, 'invoice.due_reminder', $invoice, [
            'balance' => $balance,
        ]);

        app(ResellerOpsTelegramService::class)->dueReminderSent(
            $reseller,
            (string) ($customer->customer_code ?? ''),
            (string) ($invoice->invoice_number ?? ''),
            $balance,
        );

        return true;
    }

    private function paymentUrl(Customer $customer, Invoice $invoice, float $balance): string
    {
        try {
            $link = $this->paymentLinks->create($customer, PaymentLink::PURPOSE_INVOICE, $invoice, $balance);

            return $link->publicUrl();
        } catch (\Throwable) {
            return '';
        }
    }
}
