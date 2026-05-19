<?php

namespace App\Services\Accounting;

use App\Models\Invoice;
use App\Support\TenantResolver;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class VatReportCsvExporter
{
    public function download(Carbon $from, Carbon $to, ?int $tenantId = null): StreamedResponse
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();

        $filename = 'vat-report-'.$from->format('Y-m-d').'_'.$to->format('Y-m-d').'.csv';

        $query = Invoice::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->whereBetween('issue_date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('status', ['issued', 'partial', 'paid', 'overdue'])
            ->orderBy('issue_date');

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'invoice_number', 'issue_date', 'customer', 'subtotal', 'tax_amount', 'sd_amount', 'total', 'status',
            ]);

            $query->chunkById(200, function ($invoices) use ($out): void {
                foreach ($invoices as $inv) {
                    fputcsv($out, [
                        $inv->invoice_number,
                        $inv->issue_date?->toDateString(),
                        $inv->customer?->name ?? '',
                        $inv->subtotal,
                        $inv->tax_amount,
                        $inv->sd_amount ?? 0,
                        $inv->total,
                        $inv->status,
                    ]);
                }
            });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
