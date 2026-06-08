<?php

namespace App\Services\Tenant;

use App\Models\PlatformInvoice;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

final class PlatformInvoiceBillingService
{
    public function __construct(
        private readonly TenantSubscriptionService $subscriptions,
    ) {}

    /**
     * @return array{created: int, skipped: int, tenants: list<string>}
     */
    public function generateDue(?Carbon $date = null, bool $force = false, bool $dryRun = false): array
    {
        $date = ($date ?? now())->copy()->startOfDay();
        $created = 0;
        $skipped = 0;
        $tenants = [];

        Tenant::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->each(function (Tenant $tenant) use ($date, $force, $dryRun, &$created, &$skipped, &$tenants): void {
                $result = $this->generateForTenantIfDue($tenant, $date, $force, $dryRun);
                if ($result === 'created') {
                    $created++;
                    $tenants[] = $tenant->name;
                } else {
                    $skipped++;
                }
            });

        $this->refreshOverdueStatuses();

        return compact('created', 'skipped', 'tenants');
    }

    /**
     * @return 'created'|'skipped'
     */
    public function generateForTenantIfDue(
        Tenant $tenant,
        ?Carbon $date = null,
        bool $force = false,
        bool $dryRun = false,
    ): string {
        $date = ($date ?? now())->copy()->startOfDay();
        $sub = $this->subscriptions->forTenant($tenant->id);

        if (! in_array($sub['status'], ['active', 'trial'], true)) {
            return 'skipped';
        }

        $amount = (float) $sub['monthly_fee_bdt'];
        if ($amount <= 0) {
            return 'skipped';
        }

        $billingDay = (int) $sub['billing_day'];
        if (! $force && (int) $date->day !== $billingDay) {
            return 'skipped';
        }

        $period = $date->format('Y-m');
        $exists = PlatformInvoice::query()
            ->where('tenant_id', $tenant->id)
            ->where('billing_period', $period)
            ->exists();

        if ($exists) {
            return 'skipped';
        }

        if ($dryRun) {
            return 'created';
        }

        $this->createInvoice($tenant, $sub, $period, $date);

        return 'created';
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    public function createInvoice(Tenant $tenant, array $subscription, string $period, Carbon $issueDate): PlatformInvoice
    {
        return DB::transaction(function () use ($tenant, $subscription, $period, $issueDate): PlatformInvoice {
            $dueDate = $issueDate->copy()->addDays(7);
            $amount = round((float) $subscription['monthly_fee_bdt'], 2);

            return PlatformInvoice::query()->create([
                'tenant_id' => $tenant->id,
                'billing_period' => $period,
                'plan_key' => (string) $subscription['plan_key'],
                'plan_name' => (string) $subscription['plan_name'],
                'customer_count' => (int) $subscription['customers_used'],
                'max_customers' => $subscription['max_customers'],
                'amount' => $amount,
                'status' => PlatformInvoice::STATUS_ISSUED,
                'issue_date' => $issueDate->toDateString(),
                'due_date' => $dueDate->toDateString(),
                'notes' => sprintf(
                    'ISP platform subscription — %s · %s customers',
                    $subscription['plan_name'],
                    $subscription['customers_used'],
                ),
            ]);
        });
    }

    public function markPaid(PlatformInvoice $invoice, ?string $reference = null, ?string $gateway = null): PlatformInvoice
    {
        $invoice->forceFill([
            'status' => PlatformInvoice::STATUS_PAID,
            'paid_at' => now(),
            'payment_reference' => $reference,
            'gateway' => $gateway ?? $invoice->gateway,
        ])->save();

        return $invoice->fresh();
    }

    public function refreshOverdueStatuses(): int
    {
        return PlatformInvoice::query()
            ->whereIn('status', [PlatformInvoice::STATUS_ISSUED])
            ->whereDate('due_date', '<', now()->toDateString())
            ->update(['status' => PlatformInvoice::STATUS_OVERDUE]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function latestForTenant(int $tenantId): ?array
    {
        $invoice = PlatformInvoice::query()
            ->where('tenant_id', $tenantId)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->first();

        if ($invoice === null) {
            return null;
        }

        $invoice->markOverdueIfNeeded();
        $invoice->refresh();

        return [
            'id' => $invoice->id,
            'invoice_number' => $invoice->invoice_number,
            'billing_period' => $invoice->billing_period,
            'amount' => (float) $invoice->amount,
            'status' => $invoice->status,
            'issue_date' => $invoice->issue_date?->format('Y-m-d'),
            'due_date' => $invoice->due_date?->format('Y-m-d'),
            'paid_at' => $invoice->paid_at?->toIso8601String(),
            'url' => \App\Filament\Resources\PlatformInvoiceResource::getUrl('index', [
                'tableSearch' => $invoice->invoice_number,
            ]),
            'payment_url' => app(PlatformInvoicePaymentService::class)->paymentUrl($invoice),
        ];
    }
}
