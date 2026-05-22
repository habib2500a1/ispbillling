<?php

namespace App\Services\Billing;

use Carbon\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class BillingFundFlowCsvExporter
{
    public function __construct(
        private readonly BillingFundFlowService $report,
    ) {}

    public function download(
        ?Carbon $from = null,
        ?Carbon $to = null,
        ?int $collectorId = null,
        ?string $search = null,
        bool $includeCompanyExpenses = false,
    ): StreamedResponse {
        $data = $this->report->report($from, $to, $collectorId, $search, null, $includeCompanyExpenses);
        $filename = 'bill-money-trail-'.$data['from'].'_to_'.$data['to'].'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            $s = $data['summary'];

            fputcsv($out, ['Bill money trail — summary']);
            fputcsv($out, ['Period', $data['from'].' to '.$data['to']]);
            fputcsv($out, ['Total collected (BDT)', $s['total_collected']]);
            fputcsv($out, ['Payment count', $s['payment_count']]);
            fputcsv($out, ['To invoice (BDT)', $s['to_invoice']]);
            fputcsv($out, ['To wallet (BDT)', $s['to_wallet']]);
            fputcsv($out, ['From wallet (BDT)', $s['from_wallet']]);
            fputcsv($out, ['Collector expenses (BDT)', $s['collector_expenses']]);
            fputcsv($out, ['Staff expenses (BDT)', $s['staff_expenses'] ?? 0]);
            fputcsv($out, ['Vendor expenses (BDT)', $s['vendor_expenses']]);
            fputcsv($out, ['Net after expenses (BDT)', $s['net_after_expenses']]);
            fputcsv($out, ['In collector hand (BDT)', $s['field_in_collector_hand']]);
            fputcsv($out, ['Deposited in period (BDT)', $s['field_deposited_period']]);
            fputcsv($out, []);

            fputcsv($out, ['Money flow steps']);
            fputcsv($out, ['Step', 'Amount (BDT)', 'Note']);
            foreach ($data['flow_steps'] as $step) {
                fputcsv($out, [$step['label'], $step['amount'], $step['hint']]);
            }
            fputcsv($out, []);

            if (($data['staff_expenses_by_category'] ?? []) !== []) {
                fputcsv($out, ['Staff expenses by type']);
                fputcsv($out, ['Category', 'Amount (BDT)', 'Count']);
                foreach ($data['staff_expenses_by_category'] as $row) {
                    fputcsv($out, [$row['category'], $row['total'], $row['count']]);
                }
                fputcsv($out, []);
            }

            if ($data['expenses_by_category'] !== []) {
                fputcsv($out, ['Collector field expenses by category']);
                fputcsv($out, ['Category', 'Amount (BDT)', 'Count']);
                foreach ($data['expenses_by_category'] as $row) {
                    fputcsv($out, [$row['category'], $row['total'], $row['count']]);
                }
                fputcsv($out, []);
            }

            if ($data['vendor_expenses'] !== []) {
                fputcsv($out, ['Vendor payments']);
                fputcsv($out, ['Vendor', 'Amount (BDT)', 'Count']);
                foreach ($data['vendor_expenses'] as $row) {
                    fputcsv($out, [$row['category'], $row['total'], $row['count']]);
                }
                fputcsv($out, []);
            }

            fputcsv($out, ['Payment details']);
            fputcsv($out, [
                'Date/time',
                'Receipt',
                'Collector',
                'Customer',
                'Customer ID',
                'Phone',
                'Invoice',
                'Amount (BDT)',
                'Method',
                'Type',
                'To bill (BDT)',
                'To wallet (BDT)',
                'From wallet (BDT)',
                'Destination',
                'Cash / office status',
            ]);

            foreach ($data['rows'] as $row) {
                fputcsv($out, [
                    $row['paid_at'] ?? '',
                    $row['receipt_number'] ?? '',
                    $row['collector_name'] ?? '',
                    $row['customer_name'] ?? '',
                    $row['customer_code'] ?? '',
                    $row['customer_phone'] ?? '',
                    $row['invoice_number'] ?? '',
                    $row['amount'] ?? 0,
                    $row['method_label'] ?? '',
                    $row['payment_type'] ?? '',
                    $row['to_invoice'] ?? 0,
                    $row['to_wallet'] ?? 0,
                    $row['from_wallet'] ?? 0,
                    $row['destination'] ?? '',
                    $row['cash_status'] ?? '',
                ]);
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
