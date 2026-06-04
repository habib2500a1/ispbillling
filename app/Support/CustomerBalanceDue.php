<?php

namespace App\Support;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Import\LegacyPortalDashboardSummaryProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Collectible balance: legacy portal synced BalanceDue when available, else open invoices.
 */
final class CustomerBalanceDue
{
    /** @var list<string> */
    public const OPEN_INVOICE_STATUSES = ['open', 'partial', 'sent', 'overdue'];

    /**
     * @return array{balance_due: float, invoice_due: float, payment_state: string}
     */
    public static function resolve(Customer $customer): array
    {
        $invoiceDue = self::invoiceBalanceDue($customer);
        $ispDue = self::syncedLegacyPortalBalanceDue($customer);
        $balanceDue = $ispDue !== null ? $ispDue : round(max(0, $invoiceDue), 2);

        $state = $balanceDue <= 0.009
            ? 'paid'
            : (self::customerHasPaidInvoices($customer) || ($ispDue !== null && (float) ($customer->meta['legacy_portal_paid_mtd'] ?? 0) > 0.009)
                ? 'partial'
                : 'unpaid');

        return [
            'balance_due' => $balanceDue,
            'invoice_due' => $invoiceDue,
            'payment_state' => $state,
        ];
    }

    public static function syncedLegacyPortalBalanceDue(Customer $customer): ?float
    {
        if (! LegacyPortalSource::isImportedSource($customer->import_source ?? null)) {
            return null;
        }

        $meta = is_array($customer->meta) ? $customer->meta : [];
        if (! filled(LegacyPortalSource::readMeta($meta, 'billing_synced_at'))) {
            return null;
        }

        return round(max(0, (float) LegacyPortalSource::readMeta($meta, 'balance_due', 0)), 2);
    }

    public static function amount(Customer $customer): float
    {
        if (isset($customer->resolved_balance_due)) {
            return round(max(0, (float) $customer->resolved_balance_due), 2);
        }

        return self::resolve($customer)['balance_due'];
    }

    public static function displayAmount(Customer $customer): float
    {
        if (isset($customer->resolved_balance_due)) {
            return round(max(0, (float) $customer->resolved_balance_due), 2);
        }

        return self::amount($customer);
    }

    public static function invoiceBalanceDue(Customer $customer): float
    {
        if (isset($customer->resolved_invoice_due)) {
            return round(max(0, (float) $customer->resolved_invoice_due), 2);
        }

        return self::sumOpenInvoiceDue(
            Invoice::withoutGlobalScopes()
                ->where('customer_id', $customer->id)
                ->whereIn('status', self::OPEN_INVOICE_STATUSES),
        );
    }

    public static function tenantOpenInvoiceDueSum(int $tenantId): float
    {
        return (float) Cache::remember(
            'tenant_open_invoice_due_sum:'.$tenantId,
            120,
            fn (): float => self::tenantOpenInvoiceDueSumUncached($tenantId),
        );
    }

    public static function tenantOpenInvoiceDueSumUncached(int $tenantId): float
    {
        $provider = app(LegacyPortalDashboardSummaryProvider::class);

        if ($provider->tenantUsesLegacyPortal($tenantId)) {
            $summary = $provider->summary($tenantId, false);
            if ($summary !== null && isset($summary['due'])) {
                return round(max(0, (float) $summary['due']), 2);
            }
        }

        $driver = DB::connection()->getDriverName();
        $balanceExpr = self::invoiceBalanceExpression($driver);

        return round((float) Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereIn('status', self::OPEN_INVOICE_STATUSES)
            ->selectRaw("COALESCE(SUM({$balanceExpr}), 0) as due")
            ->value('due'), 2);
    }

    public static function sumOpenInvoiceDue(Builder $query): float
    {
        $balanceExpr = self::invoiceBalanceExpression($query->getModel()->getConnection()->getDriverName());

        return round((float) (clone $query)
            ->selectRaw("COALESCE(SUM({$balanceExpr}), 0) as due")
            ->value('due'), 2);
    }

    public static function invoiceBalanceExpression(string $driver): string
    {
        return match ($driver) {
            'sqlite' => 'MAX(0, total - amount_paid)',
            default => 'GREATEST(0, total - amount_paid)',
        };
    }

    public static function invoiceBalanceExpressionForConnection(?string $connection = null): string
    {
        $driver = DB::connection($connection)->getDriverName();

        return self::invoiceBalanceExpression($driver);
    }

    public static function sumOpenInvoiceBalanceSelect(string $alias = 'due'): string
    {
        $expr = self::invoiceBalanceExpressionForConnection();

        return "COALESCE(SUM({$expr}), 0) as {$alias}";
    }

    public static function invoiceBalanceExpressionAliased(string $tableAlias, ?string $connection = null): string
    {
        $expr = self::invoiceBalanceExpressionForConnection($connection);

        return (string) preg_replace(
            '/\b(total|amount_paid)\b/',
            "{$tableAlias}.$1",
            $expr,
        );
    }

    /**
     * SQL expression: open-invoice balance due only.
     */
    public static function resolvedBalanceDueExpression(string $table, string $driver): string
    {
        $statusList = implode(',', array_map(
            static fn (string $s): string => "'".$s."'",
            self::OPEN_INVOICE_STATUSES,
        ));

        $balanceExpr = self::invoiceBalanceExpression($driver);
        $invoiceDueSql = "(SELECT COALESCE(SUM({$balanceExpr}), 0) FROM invoices WHERE invoices.customer_id = {$table}.id AND invoices.status IN ({$statusList}))";

        if ($driver === 'pgsql') {
            $sources = implode(',', array_map(
                static fn (string $s): string => "'".$s."'",
                LegacyPortalSource::importSourceValues(),
            ));
            $portalDue = "COALESCE(NULLIF({$table}.meta->>'legacy_portal_balance_due', '')::numeric, NULLIF({$table}.meta->>'isp_digital_balance_due', '')::numeric, 0)";
            $syncedAt = "COALESCE(NULLIF({$table}.meta->>'legacy_portal_billing_synced_at', ''), NULLIF({$table}.meta->>'isp_digital_billing_synced_at', ''))";

            return "(CASE WHEN {$table}.import_source IN ({$sources}) AND {$syncedAt} IS NOT NULL AND {$syncedAt} <> '' THEN GREATEST(0, {$portalDue}) ELSE {$invoiceDueSql} END)";
        }

        return $invoiceDueSql;
    }

    public static function augmentTableQuery(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();
        $driver = $query->getConnection()->getDriverName();
        $statusList = implode(',', array_map(
            static fn (string $s): string => "'".$s."'",
            self::OPEN_INVOICE_STATUSES,
        ));

        $balanceExpr = self::invoiceBalanceExpression($driver);
        $invoiceDueSql = "(SELECT COALESCE(SUM({$balanceExpr}), 0) FROM invoices WHERE invoices.customer_id = {$table}.id AND invoices.status IN ({$statusList}))";
        $invoiceCountSql = "(SELECT COUNT(*) FROM invoices WHERE invoices.customer_id = {$table}.id)";
        $resolvedSql = self::resolvedBalanceDueExpression($table, $driver);

        // addSelect alone replaces the implicit `table.*` and drops the primary key (breaks Filament record URLs).
        if ($query->getQuery()->columns === null) {
            $query->select("{$table}.*");
        } elseif (! self::querySelectsPrimaryKey($query, $table)) {
            $query->addSelect("{$table}.id");
        }

        return $query->addSelect([
            DB::raw("{$invoiceDueSql} as resolved_invoice_due"),
            DB::raw("{$invoiceCountSql} as resolved_invoice_count"),
            DB::raw("{$resolvedSql} as resolved_balance_due"),
        ]);
    }

    private static function querySelectsPrimaryKey(Builder $query, string $table): bool
    {
        foreach ($query->getQuery()->columns ?? [] as $column) {
            $expression = (string) $column;

            if ($expression === "{$table}.*" || $expression === '*') {
                return true;
            }

            if (preg_match('/\b'.preg_quote($table, '/').'\.id\b/i', $expression) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Drop legacy ISP meta due fields; keep payment state aligned with invoices.
     */
    public static function refreshMetaAfterPayment(Customer $customer): void
    {
        $customer->refresh();
        $resolved = self::resolve($customer);
        $meta = is_array($customer->meta) ? $customer->meta : [];

        $meta['balance_due'] = $resolved['balance_due'];
        $meta['billing_payment_state'] = $resolved['payment_state'];
        $meta['local_due_synced_at'] = now()->toIso8601String();

        foreach (self::legacyMetaDueKeys() as $legacyKey) {
            unset($meta[$legacyKey]);
        }

        $customer->updateQuietly(['meta' => $meta]);
    }

    /**
     * @return list<string>
     */
    public static function legacyMetaDueKeys(): array
    {
        return [
            'legacy_portal_balance_due',
            'legacy_portal_payment_state',
        ];
    }

    private static function customerHasInvoices(Customer $customer): bool
    {
        if (isset($customer->resolved_invoice_count)) {
            return ((int) $customer->resolved_invoice_count) > 0;
        }

        return Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->exists();
    }

    private static function customerHasPaidInvoices(Customer $customer): bool
    {
        return Invoice::withoutGlobalScopes()
            ->where('customer_id', $customer->id)
            ->where('status', 'paid')
            ->exists();
    }
}
