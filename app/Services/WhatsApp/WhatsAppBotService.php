<?php

namespace App\Services\WhatsApp;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Package;
use App\Models\SalesLead;
use App\Models\SupportTicket;
use App\Services\BillPayment\PaymentLinkService;
use App\Support\CustomerStatus;

final class WhatsAppBotService
{
    public function __construct(
        private readonly WhatsAppOutboundService $outbound,
        private readonly PaymentLinkService $paymentLinks,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Meta webhook JSON
     */
    public function handleWebhookPayload(array $payload): void
    {
        if (! config('whatsapp_bot.enabled', false)) {
            return;
        }

        $entries = $payload['entry'] ?? [];
        if (! is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $changes = $entry['changes'] ?? [];
            if (! is_array($changes)) {
                continue;
            }
            foreach ($changes as $change) {
                $value = $change['value'] ?? [];
                if (! is_array($value)) {
                    continue;
                }
                $messages = $value['messages'] ?? [];
                if (! is_array($messages)) {
                    continue;
                }
                foreach ($messages as $message) {
                    $this->handleIncomingMessage($message);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $message
     */
    public function handleIncomingMessage(array $message): void
    {
        $from = (string) ($message['from'] ?? '');
        $text = trim((string) ($message['text']['body'] ?? ''));
        if ($from === '' || $text === '') {
            return;
        }

        $reply = $this->buildReply($from, $text);
        if ($reply !== null && $reply !== '') {
            $this->outbound->sendText($from, $reply);
        }
    }

    public function buildReply(string $phoneDigits, string $text): string
    {
        $normalized = $this->normalizeCommand($text);
        $customer = $this->findCustomerByPhone($phoneDigits);

        if (in_array($normalized, ['MENU', 'HELP', 'মেনু', 'সাহায্য'], true)) {
            return __('whatsapp.menu');
        }

        if ($customer === null) {
            if (in_array($normalized, ['PACKAGES', 'PACKAGE', 'প্যাকেজ'], true)) {
                return $this->replyPackages();
            }
            if (str_starts_with($normalized, 'SUPPORT')) {
                return $this->openAnonymousLead($phoneDigits, $this->extractSupportBody($text));
            }

            return __('whatsapp.unknown_customer');
        }

        return match (true) {
            in_array($normalized, ['BALANCE', 'ব্যালান্স', 'BAL'], true) => $this->replyBalance($customer),
            in_array($normalized, ['BILL', 'বিল', 'DUE'], true) => $this->replyBill($customer),
            in_array($normalized, ['PAY', 'পে'], true) => $this->replyPayLink($customer),
            in_array($normalized, ['PACKAGES', 'PACKAGE', 'প্যাকেজ'], true) => $this->replyPackages(),
            in_array($normalized, ['TICKET', 'TICKETS', 'টিকেট'], true) => $this->replyTickets($customer),
            str_starts_with($normalized, 'SUPPORT') => $this->replySupport($customer, $this->extractSupportBody($text)),
            default => __('whatsapp.help'),
        };
    }

    private function normalizeCommand(string $text): string
    {
        $upper = mb_strtoupper(trim($text));

        return preg_replace('/\s+/', ' ', $upper) ?? $upper;
    }

    private function extractSupportBody(string $text): string
    {
        $body = preg_replace('/^support\s+/i', '', trim($text)) ?? trim($text);

        return $body !== '' ? $body : 'WhatsApp support request';
    }

    private function findCustomerByPhone(string $phoneDigits): ?Customer
    {
        $digits = preg_replace('/\D+/', '', $phoneDigits) ?? '';

        return Customer::query()
            ->withoutGlobalScopes()
            ->where('status', CustomerStatus::ACTIVE)
            ->where(function ($q) use ($digits, $phoneDigits): void {
                $q->where('phone', $digits)
                    ->orWhere('phone', $phoneDigits);
                if (strlen($digits) >= 10) {
                    $q->orWhere('phone', 'like', '%'.substr($digits, -10));
                }
            })
            ->first();
    }

    private function replyBalance(Customer $customer): string
    {
        return __('whatsapp.balance', [
            'amount' => number_format((float) $customer->account_balance, 2),
            'code' => $customer->customer_code,
        ]);
    }

    private function replyBill(Customer $customer): string
    {
        $invoice = $this->latestDueInvoice($customer);

        if ($invoice === null) {
            return __('whatsapp.no_due');
        }

        return __('whatsapp.due', [
            'number' => $invoice->invoice_number,
            'amount' => number_format($invoice->balanceDue(), 2),
            'due' => $invoice->due_date?->format('d M Y') ?? '—',
            'url' => $this->payUrlForInvoice($customer, $invoice),
        ]);
    }

    private function replyPayLink(Customer $customer): string
    {
        $invoice = $this->latestDueInvoice($customer);
        if ($invoice === null) {
            return __('whatsapp.no_due');
        }

        $link = $this->paymentLinks->create(
            $customer,
            \App\Models\PaymentLink::PURPOSE_INVOICE,
            $invoice,
            $invoice->balanceDue(),
        );

        return __('whatsapp.pay_link', [
            'number' => $invoice->invoice_number,
            'amount' => number_format($invoice->balanceDue(), 2),
            'url' => $link->publicUrl(),
        ]);
    }

    private function replyPackages(): string
    {
        $packages = Package::query()
            ->where('is_active', true)
            ->orderBy('price_monthly')
            ->limit(8)
            ->get(['name', 'download_mbps', 'upload_mbps', 'price_monthly']);

        if ($packages->isEmpty()) {
            return __('whatsapp.no_packages');
        }

        $lines = $packages->map(fn (Package $p): string => sprintf(
            '• %s — %d/%d Mbps — %s BDT/mo',
            $p->name,
            (int) $p->download_mbps,
            (int) $p->upload_mbps,
            number_format((float) $p->price_monthly, 0),
        ))->implode("\n");

        return __('whatsapp.packages_header')."\n".$lines;
    }

    private function replyTickets(Customer $customer): string
    {
        $tickets = SupportTicket::query()
            ->where('customer_id', $customer->id)
            ->whereNotIn('status', ['closed'])
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get(['ticket_number', 'status', 'subject']);

        if ($tickets->isEmpty()) {
            return __('whatsapp.no_tickets');
        }

        $lines = $tickets->map(fn (SupportTicket $t): string => sprintf(
            '#%s [%s] %s',
            $t->ticket_number,
            strtoupper((string) $t->status),
            mb_substr((string) $t->subject, 0, 40),
        ))->implode("\n");

        return __('whatsapp.tickets_header')."\n".$lines;
    }

    private function replySupport(Customer $customer, string $body): string
    {
        $ticket = SupportTicket::query()->create([
            'tenant_id' => $customer->tenant_id,
            'customer_id' => $customer->id,
            'channel' => 'whatsapp',
            'department' => config('whatsapp_bot.default_department', 'technical_support'),
            'priority' => 'medium',
            'subject' => mb_substr($body, 0, 200),
            'description' => $body,
            'status' => 'open',
        ]);

        return __('whatsapp.ticket_opened', ['number' => $ticket->ticket_number]);
    }

    private function openAnonymousLead(string $phone, string $body): string
    {
        SalesLead::query()->create([
            'name' => 'WhatsApp '.$phone,
            'phone' => $phone,
            'source' => 'whatsapp',
            'status' => SalesLead::STATUS_NEW,
            'notes' => $body,
        ]);

        return __('whatsapp.lead_recorded');
    }

    private function latestDueInvoice(Customer $customer): ?Invoice
    {
        return Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['issued', 'partial', 'overdue'])
            ->orderByDesc('due_date')
            ->first();
    }

    private function payUrlForInvoice(Customer $customer, Invoice $invoice): string
    {
        if (! config('whatsapp_bot.send_payment_links', true)) {
            return url('/pay');
        }

        return $this->paymentLinks->create(
            $customer,
            \App\Models\PaymentLink::PURPOSE_INVOICE,
            $invoice,
            $invoice->balanceDue(),
        )->publicUrl();
    }
}
