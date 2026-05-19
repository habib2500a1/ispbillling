<?php

namespace App\Services\Billing;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PaymentResource;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Support\PaymentType;
use Illuminate\Support\Collection;

final class BillCollectionSearchService
{
    /**
     * @return Collection<int, array<string, mixed>>
     */
    public function search(string $query, int $limit = 25): Collection
    {
        $query = trim($query);
        if (mb_strlen($query) < 2) {
            return collect();
        }

        $digits = preg_replace('/\D+/', '', $query) ?? '';
        $like = '%'.$query.'%';

        $customers = Customer::query()
            ->with(['area', 'zone', 'subzone', 'package'])
            ->where(function ($w) use ($query, $like, $digits): void {
                $w->where('customer_code', 'like', $like)
                    ->orWhere('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('address', 'like', $like)
                    ->orWhere('mikrotik_secret_name', 'like', $like)
                    ->orWhere('radius_username', 'like', $like)
                    ->orWhere('nid_number', 'like', $like);

                if ($digits !== '') {
                    $w->orWhere('phone', 'like', '%'.$digits.'%')
                        ->orWhere('customer_code', 'like', '%'.$digits.'%');
                } else {
                    $w->orWhere('phone', 'like', $like);
                }

                $w->orWhereHas('invoices', function ($iq) use ($like): void {
                    $iq->where('invoice_number', 'like', $like);
                })
                    ->orWhereHas('area', fn ($aq) => $aq->where('name', 'like', $like))
                    ->orWhereHas('zone', fn ($zq) => $zq->where('name', 'like', $like))
                    ->orWhereHas('subzone', fn ($sq) => $sq->where('name', 'like', $like));
            })
            ->limit($limit * 2)
            ->get()
            ->sortBy(function (Customer $customer) use ($query): int {
                if (strcasecmp((string) $customer->customer_code, $query) === 0) {
                    return 0;
                }
                if (str_starts_with(strtolower((string) $customer->customer_code), strtolower($query))) {
                    return 1;
                }
                if (str_contains(strtolower((string) $customer->name), strtolower($query))) {
                    return 2;
                }

                return 3;
            })
            ->take($limit);

        return $customers->map(fn (Customer $customer): array => $this->present($customer))->values();
    }

    public function find(int $customerId): ?array
    {
        $customer = Customer::query()
            ->with(['area', 'zone', 'subzone', 'package'])
            ->find($customerId);

        return $customer ? $this->present($customer, detailed: true) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Customer $customer, bool $detailed = false): array
    {
        $openInvoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial'])
            ->orderBy('due_date')
            ->get();

        $balanceDue = round($openInvoices->sum(fn (Invoice $inv): float => $inv->balanceDue()), 2);

        $row = [
            'id' => $customer->id,
            'customer_code' => $customer->customer_code,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'username' => $customer->pppLoginName(),
            'address' => $customer->formattedAddress(),
            'status' => $customer->status,
            'package' => $customer->package?->name,
            'balance_due' => $balanceDue,
            'open_invoices' => $openInvoices->count(),
            'account_balance' => (float) $customer->account_balance,
        ];

        if ($detailed) {
            $row['invoices'] = $openInvoices->map(fn (Invoice $inv): array => $this->invoiceRow($inv))->values()->all();

            $allInvoices = Invoice::query()
                ->where('customer_id', $customer->id)
                ->orderByDesc('issue_date')
                ->orderByDesc('id')
                ->limit(30)
                ->get();

            $row['bill_history'] = $allInvoices
                ->map(fn (Invoice $inv): array => $this->invoiceRow($inv))
                ->values()
                ->all();

            $row['collection_history'] = Payment::query()
                ->where('customer_id', $customer->id)
                ->with(['invoice:id,invoice_number', 'recorder:id,name'])
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->limit(40)
                ->get()
                ->map(fn (Payment $payment): array => $this->paymentRow($payment))
                ->values()
                ->all();
        }

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceRow(Invoice $inv): array
    {
        return [
            'id' => $inv->id,
            'invoice_number' => $inv->invoice_number,
            'issue_date' => $inv->issue_date?->toDateString(),
            'due_date' => $inv->due_date?->toDateString(),
            'total' => round((float) $inv->total, 2),
            'amount_paid' => round((float) $inv->amount_paid, 2),
            'balance_due' => $inv->balanceDue(),
            'status' => $inv->status,
            'is_overdue' => $inv->isOverdue(),
            'edit_url' => InvoiceResource::getUrl('edit', ['record' => $inv]),
            'pdf_url' => route('invoices.pdf', $inv),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function paymentRow(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'paid_at' => $payment->paid_at?->format('Y-m-d H:i') ?? '—',
            'amount' => round((float) $payment->amount, 2),
            'method' => $payment->methodLabel(),
            'status' => $payment->status,
            'payment_type' => $payment->typeLabel(),
            'invoice_id' => $payment->invoice_id,
            'invoice_number' => $payment->invoice?->invoice_number,
            'recorded_by' => $payment->recorder?->name ?? '—',
            'recorded_by_id' => $payment->recorded_by,
            'reference' => $payment->reference,
            'notes' => $payment->notes,
            'receipt_url' => route('payments.receipt', $payment),
            'edit_url' => PaymentResource::getUrl('edit', ['record' => $payment]),
            'can_correct' => $payment->status === 'completed'
                && in_array($payment->payment_type ?? PaymentType::PAYMENT, [PaymentType::PAYMENT, PaymentType::WALLET_APPLY], true),
        ];
    }
}
