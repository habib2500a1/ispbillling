<?php

namespace App\Services\Billing;

use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CollectionReportCsvExporter
{
    public function __construct(
        private readonly CollectionDeskReportService $report,
    ) {}

    public function download(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $collectorId = null,
        ?string $search = null,
        ?int $customerId = null,
        ?string $sourceFilter = null,
        ?string $methodFilter = null,
    ): StreamedResponse {
        $data = $this->report->report($from, $to, $collectorId, $search, null, $customerId, $sourceFilter, $methodFilter);
        $filename = 'collection-report-'.$data['from'].'_to_'.$data['to'].'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Date',
                'Time',
                'Bill #',
                'Receipt',
                'User (PPP)',
                'Collection type',
                'Source',
                'Received by',
                'Approved by',
                'Created by',
                'Customer name',
                'Customer ID',
                'Phone',
                'Area',
                'Invoice',
                'Bill total',
                'Received',
                'VAT',
                'Discount',
                'Balance due',
                'Method',
                'Reference',
                'Gateway TrxID',
                'Notes',
                'Valid until',
                'Off from',
                'Network',
                'GPS',
                'Latitude',
                'Longitude',
            ]);

            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['date'],
                    $row['time'],
                    $row['bill_number'] ?? '',
                    $row['receipt_number'],
                    $row['username'] ?? '',
                    $row['collection_label'] ?? '',
                    $row['source_label'] ?? '',
                    $row['received_by'] ?? '',
                    $row['approved_by'] ?? '',
                    $row['created_by'] ?? '',
                    $row['customer_name'],
                    $row['customer_code'],
                    $row['customer_phone'],
                    $row['customer_area'] ?? '',
                    $row['invoice_number'] ?? '',
                    $row['bill_total'] ?? '',
                    $row['amount'],
                    $row['vat'] ?? 0,
                    $row['discount'] ?? '',
                    $row['balance_due'] ?? '',
                    $row['method_label'],
                    $row['reference'] ?? '',
                    $row['gateway_transaction_id'] ?? '',
                    $row['notes'] ?? '',
                    $row['service_valid_until'] ?? '',
                    $row['service_off_date'] ?? '',
                    $row['network_state'] ?? '',
                    $row['has_gps'] ? 'yes' : 'no',
                    $row['latitude'] ?? '',
                    $row['longitude'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
