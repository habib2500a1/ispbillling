<?php

namespace App\Services\Billing;

use App\Filament\Resources\InvoiceResource;
use App\Filament\Resources\PendingGatewayPaymentResource;
use App\Models\Invoice;
use App\Models\PendingGatewayPayment;
use App\Support\PaymentAdminAccess;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\Cache;

/**
 * Admin billing notice board — MFS verify pending, overdue bills, due soon.
 */
final class AdminBillingNoticesService
{
    /**
     * @return array<string, mixed>
     */
    public function payload(?int $tenantId = null): array
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $userId = (int) (auth()->id() ?? 0);

        return Cache::remember(
            "admin_billing_notices:{$tenantId}:{$userId}:".now()->format('Y-m-d-H-i'),
            60,
            fn (): array => $this->build($tenantId),
        );
    }

    public function actionableCount(?int $tenantId = null): int
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        return (int) ($this->payload($tenantId)['summary']['total'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    private function build(int $tenantId): array
    {
        $mfs = $this->mfsPendingSection($tenantId);
        $overdue = $this->overdueBillsSection($tenantId);
        $dueSoon = $this->dueSoonSection($tenantId);

        $sections = array_values(array_filter([$mfs, $overdue, $dueSoon]));

        return [
            'updated_at' => now()->toIso8601String(),
            'summary' => [
                'mfs_pending' => count($mfs['items'] ?? []),
                'overdue' => count($overdue['items'] ?? []),
                'due_soon' => count($dueSoon['items'] ?? []),
                'total' => collect($sections)->sum(fn (array $s): int => count($s['items'] ?? [])),
            ],
            'sections' => $sections,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mfsPendingSection(int $tenantId): ?array
    {
        if (! PaymentAdminAccess::canViewPaymentOps()) {
            return null;
        }

        $listUrl = PendingGatewayPaymentResource::getUrl();

        $items = PendingGatewayPayment::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->with(['customer:id,name,customer_code,mikrotik_secret_name', 'invoice:id,invoice_number'])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get()
            ->map(function (PendingGatewayPayment $row) use ($listUrl): array {
                $login = $row->customer?->mikrotik_secret_name
                    ?: $row->customer?->customer_code
                    ?: $row->customer?->name
                    ?: '—';
                $bill = $row->invoice?->invoice_number ?? '—';

                return [
                    'id' => 'mfs-'.$row->id,
                    'severity' => 'warning',
                    'title' => strtoupper((string) $row->gateway).' · '.number_format((float) $row->amount, 2).' BDT',
                    'message' => sprintf(
                        'TrxID %s — %s — bill %s — %s',
                        $row->transaction_id,
                        $login,
                        $bill,
                        $row->created_at?->diffForHumans() ?? ''
                    ),
                    'url' => $listUrl,
                    'meta' => [
                        'gateway' => strtoupper((string) $row->gateway),
                        'trx' => (string) $row->transaction_id,
                        'amount' => number_format((float) $row->amount, 2),
                        'customer' => (string) ($row->customer?->name ?? $login),
                        'bill' => $bill,
                        'when' => $row->created_at?->diffForHumans() ?? '',
                    ],
                ];
            })
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'key' => 'mfs_pending',
            'title' => 'MFS payment — verify pending',
            'hint' => 'Wrong TrxID or SMS mismatch — approve from pending gateway payments.',
            'severity' => 'warning',
            'url' => $listUrl,
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function overdueBillsSection(int $tenantId): ?array
    {
        if (! InvoiceResource::canViewAny()) {
            return null;
        }

        $items = $this->openInvoicesWithBalance($tenantId, overdueOnly: true, limit: 20);

        if ($items === []) {
            return null;
        }

        return [
            'key' => 'overdue_bills',
            'title' => 'Overdue bills (urgent)',
            'hint' => 'Past due date — collect or suspend per policy.',
            'severity' => 'danger',
            'url' => InvoiceResource::getUrl('due'),
            'items' => $items,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function dueSoonSection(int $tenantId): ?array
    {
        if (! InvoiceResource::canViewAny()) {
            return null;
        }

        $today = now()->toDateString();
        $limit = now()->addDays(3)->toDateString();

        $items = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'partial'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $limit)
            ->whereRaw('(total - amount_paid) > 0.009')
            ->with('customer:id,name,customer_code,mikrotik_secret_name')
            ->orderBy('due_date')
            ->limit(15)
            ->get()
            ->map(fn (Invoice $inv): array => $this->invoiceNoticeRow($inv, 'due_soon'))
            ->all();

        if ($items === []) {
            return null;
        }

        return [
            'key' => 'due_soon',
            'title' => 'Due within 3 days',
            'hint' => 'Upcoming due — remind subscriber before overdue.',
            'severity' => 'amber',
            'url' => InvoiceResource::getUrl('due'),
            'items' => $items,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function openInvoicesWithBalance(int $tenantId, bool $overdueOnly, int $limit): array
    {
        $q = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', ['open', 'partial'])
            ->whereRaw('(total - amount_paid) > 0.009')
            ->with('customer:id,name,customer_code,mikrotik_secret_name');

        if ($overdueOnly) {
            $q->whereNotNull('due_date')
                ->whereDate('due_date', '<', now()->toDateString());
        }

        return $q->orderBy('due_date')
            ->limit($limit)
            ->get()
            ->map(fn (Invoice $inv): array => $this->invoiceNoticeRow($inv, 'overdue'))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function invoiceNoticeRow(Invoice $inv, string $kind): array
    {
        $due = round((float) $inv->total - (float) $inv->amount_paid, 2);
        $login = $inv->customer?->mikrotik_secret_name
            ?: $inv->customer?->customer_code
            ?: $inv->customer?->name
            ?: '—';
        $dueDate = $inv->due_date?->format('d M Y') ?? '—';
        $days = $inv->due_date ? now()->startOfDay()->diffInDays($inv->due_date, false) : null;

        $urgency = match (true) {
            $kind === 'overdue' => 'danger',
            $days !== null && $days <= 1 => 'warning',
            default => 'amber',
        };

        $title = $kind === 'overdue'
            ? 'Overdue · '.$inv->invoice_number
            : 'Due soon · '.$inv->invoice_number;

        $message = $kind === 'overdue'
            ? sprintf('%s — %s BDT due — was due %s', $login, number_format($due, 2), $dueDate)
            : sprintf('%s — %s BDT due — due %s', $login, number_format($due, 2), $dueDate);

        return [
            'id' => 'inv-'.$inv->id,
            'severity' => $urgency,
            'title' => $title,
            'message' => $message,
            'url' => InvoiceResource::getUrl('edit', ['record' => $inv]),
            'meta' => [
                'invoice' => $inv->invoice_number,
                'customer' => (string) ($inv->customer?->name ?? $login),
                'login' => (string) $login,
                'due_bdt' => number_format($due, 2),
                'due_date' => $dueDate,
            ],
        ];
    }
}
