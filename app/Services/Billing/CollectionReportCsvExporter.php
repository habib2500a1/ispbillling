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
    ): StreamedResponse {
        $data = $this->report->report($from, $to, $collectorId, $search, null, $customerId);
        $filename = 'collection-report-'.$data['from'].'_to_'.$data['to'].'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Date',
                'Time',
                'Receipt',
                'Collector',
                'Collector email',
                'Customer name',
                'Customer ID',
                'Phone',
                'Area',
                'Invoice',
                'Amount (BDT)',
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
                    $row['receipt_number'],
                    $row['collector_name'],
                    $row['collector_email'] ?? '',
                    $row['customer_name'],
                    $row['customer_code'],
                    $row['customer_phone'],
                    $row['customer_area'] ?? '',
                    $row['invoice_number'] ?? '',
                    $row['amount'],
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
