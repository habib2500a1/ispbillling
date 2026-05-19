<?php

namespace App\Services\Billing;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\PaymentLink;
use App\Services\BillPayment\PaymentLinkService;
use App\Services\Notifications\NotificationDispatcher;
use Illuminate\Support\Facades\Cache;

final class DunningLadderService
{
    public function __construct(
        private readonly NotificationDispatcher $dispatcher,
        private readonly PaymentLinkService $paymentLinks,
    ) {}

    /**
     * @return array{sent: int, skipped: int}
     */
    public function run(bool $dryRun = false): array
    {
        if (! config('billing.dunning.enabled', true)) {
            return ['sent' => 0, 'skipped' => 0];
        }

        $stages = config('billing.dunning.stages', []);
        $sent = 0;
        $skipped = 0;

        foreach ($stages as $stage) {
            $eventKey = (string) ($stage['key'] ?? '');
            $offset = (int) ($stage['offset_days'] ?? 0);
            if ($eventKey === '') {
                continue;
            }

            if (! $this->eventEnabled($eventKey)) {
                continue;
            }

            $targetDate = now()->startOfDay()->addDays($offset)->toDateString();

            $query = Invoice::query()
                ->with('customer')
                ->whereIn('status', ['open', 'partial'])
                ->whereRaw('(total - amount_paid) > 0')
                ->whereDate('due_date', $targetDate);

            foreach ($query->cursor() as $invoice) {
                /** @var Invoice $invoice */
                $customer = $invoice->customer;
                if (! $customer instanceof Customer || $customer->status !== 'active') {
                    $skipped++;

                    continue;
                }

                $cacheKey = "dunning:{$eventKey}:{$invoice->id}";
                if (Cache::has($cacheKey)) {
                    $skipped++;

                    continue;
                }

                $balance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);

                if ($dryRun) {
                    $sent++;

                    continue;
                }

                $variables = [
                    'invoice_number' => $invoice->invoice_number ?? '—',
                    'balance' => number_format($balance, 2),
                    'due_date' => $invoice->due_date?->toFormattedDateString() ?? '—',
                    'payment_url' => $this->paymentUrlForInvoice($customer, $invoice, $balance, $eventKey),
                ];

                $this->dispatcher->notifyCustomer($customer, $eventKey, $variables, [
                    'subject' => $this->subjectForStage($eventKey, $invoice->invoice_number ?? ''),
                ]);

                Cache::put($cacheKey, true, now()->addDays(2));
                $sent++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    private function eventEnabled(string $eventKey): bool
    {
        if ((bool) config("notifications.events.{$eventKey}.enabled", false)) {
            return true;
        }

        return (bool) env('SMS_REMINDERS_ENABLED', false)
            || (bool) config('notifications.events.invoice_due.enabled', false);
    }

    private function subjectForStage(string $eventKey, string $invoiceNumber): string
    {
        return match ($eventKey) {
            'invoice_due_soon' => "Invoice {$invoiceNumber} due soon",
            'invoice_due_today' => "Invoice {$invoiceNumber} due today",
            'invoice_overdue_3' => "Overdue: {$invoiceNumber}",
            'invoice_overdue_7' => "Urgent overdue: {$invoiceNumber}",
            'invoice_overdue_14' => "Final notice: {$invoiceNumber}",
            default => "Billing reminder: {$invoiceNumber}",
        };
    }

    private function paymentUrlForInvoice(Customer $customer, Invoice $invoice, float $balance, string $eventKey): string
    {
        if (! config('billing.dunning.include_payment_link', true)) {
            return route('bill-payment.index');
        }

        $link = $this->paymentLinks->create(
            $customer,
            PaymentLink::PURPOSE_INVOICE,
            $invoice,
            $balance > 0 ? $balance : null,
            null,
            'dunning:'.$eventKey,
        );

        return $link->publicUrl();
    }

}
