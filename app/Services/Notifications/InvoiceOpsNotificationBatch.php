<?php

namespace App\Services\Notifications;

use App\Models\Invoice;

/**
 * Accumulates invoice ops alerts during bulk billing and sends one Telegram digest at flush().
 */
final class InvoiceOpsNotificationBatch
{
    private static bool $active = false;

    private static string $runLabel = '';

    /** @var array<int, list<Invoice>> */
    private static array $invoicesByTenant = [];

    public function start(string $runLabel): void
    {
        self::$active = true;
        self::$runLabel = $runLabel;
        self::$invoicesByTenant = [];
    }

    public function isActive(): bool
    {
        return self::$active;
    }

    public function record(Invoice $invoice): void
    {
        if (! self::$active) {
            return;
        }

        $tenantId = (int) $invoice->tenant_id;
        self::$invoicesByTenant[$tenantId] ??= [];
        self::$invoicesByTenant[$tenantId][] = $invoice;
    }

    public function flush(): void
    {
        if (! self::$active) {
            return;
        }

        self::$active = false;
        $runLabel = self::$runLabel;
        $byTenant = self::$invoicesByTenant;
        self::$invoicesByTenant = [];
        self::$runLabel = '';

        $ops = app(OpsNotificationService::class);

        foreach ($byTenant as $tenantId => $invoices) {
            if ($invoices === []) {
                continue;
            }

            $ops->onInvoiceCreatedBulkDigest((int) $tenantId, $invoices, $runLabel);
        }
    }

    public function reset(): void
    {
        self::$active = false;
        self::$runLabel = '';
        self::$invoicesByTenant = [];
    }
}
