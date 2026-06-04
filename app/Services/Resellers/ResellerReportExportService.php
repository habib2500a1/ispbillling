<?php

namespace App\Services\Resellers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Reseller;
use App\Models\ResellerBalanceTransfer;
use App\Models\ResellerCommission;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ResellerReportExportService
{
    /**
     * @return array{headers: list<string>, rows: list<list<string|float|null>>}
     */
    public function dataset(Reseller $reseller, string $type, Carbon $from, Carbon $to): array
    {
        return match ($type) {
            'commission' => $this->commissionRows($reseller, $from, $to),
            'wallet' => $this->walletRows($reseller, $from, $to),
            'due' => $this->dueRows($reseller),
            'clients' => $this->clientRows($reseller),
            default => $this->collectionRows($reseller, $from, $to),
        };
    }

    public function filename(Reseller $reseller, string $type, Carbon $from, Carbon $to, string $format): string
    {
        $slug = $type === 'collection' ? 'collection' : $type;
        $ext = $format === 'xlsx' ? 'xlsx' : 'csv';

        return sprintf(
            'reseller-%s-%s-%s-%s.%s',
            $slug,
            $reseller->code,
            $from->format('Ymd'),
            $to->format('Ymd'),
            $ext,
        );
    }

    public function streamedCsv(Reseller $reseller, string $type, Carbon $from, Carbon $to): StreamedResponse
    {
        $dataset = $this->dataset($reseller, $type, $from, $to);
        $filename = $this->filename($reseller, $type, $from, $to, 'csv');

        return response()->streamDownload(function () use ($dataset): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $dataset['headers']);
            foreach ($dataset['rows'] as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function xlsxBinary(Reseller $reseller, string $type, Carbon $from, Carbon $to): string
    {
        $dataset = $this->dataset($reseller, $type, $from, $to);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(ucfirst($type));

        $sheet->fromArray($dataset['headers'], null, 'A1');
        if ($dataset['rows'] !== []) {
            $sheet->fromArray($dataset['rows'], null, 'A2');
        }

        $lastCol = $this->columnLetter(count($dataset['headers']));
        $sheet->getStyle('A1:'.$lastCol.'1')->getFont()->setBold(true);
        foreach (range('A', $lastCol) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $meta = $spreadsheet->createSheet();
        $meta->setTitle('Info');
        $meta->fromArray([
            ['Partner', $reseller->name.' ('.$reseller->code.')'],
            ['Report', ucfirst($type)],
            ['From', $from->toDateString()],
            ['To', $to->toDateString()],
            ['Generated', now()->toDateTimeString()],
        ], null, 'A1');
        $meta->getColumnDimension('A')->setWidth(14);
        $meta->getColumnDimension('B')->setWidth(40);

        $spreadsheet->setActiveSheetIndex(0);

        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');

        return (string) ob_get_clean();
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function collectionRows(Reseller $reseller, Carbon $from, Carbon $to): array
    {
        $customerIds = $reseller->customers()->pluck('id');
        $headers = ['Date', 'Subscriber', 'Name', 'Amount BDT', 'Method', 'Reference', 'Receipt'];

        if ($customerIds->isEmpty()) {
            return ['headers' => $headers, 'rows' => []];
        }

        $rows = Payment::query()
            ->whereIn('customer_id', $customerIds)
            ->where('status', 'completed')
            ->whereBetween('paid_at', [$from, $to])
            ->with('customer:id,customer_code,name')
            ->orderByDesc('paid_at')
            ->get()
            ->map(fn (Payment $row): array => [
                $row->paid_at?->format('Y-m-d H:i') ?? '',
                $row->customer?->customer_code ?? '—',
                $row->customer?->name ?? '—',
                number_format((float) $row->amount, 2, '.', ''),
                (string) $row->method,
                (string) ($row->reference ?? ''),
                (string) ($row->receipt_number ?? ''),
            ])
            ->all();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function commissionRows(Reseller $reseller, Carbon $from, Carbon $to): array
    {
        $headers = ['Date', 'Subscriber', 'Name', 'Payment BDT', 'Commission BDT', 'Status'];

        $rows = ResellerCommission::query()
            ->where('reseller_id', $reseller->id)
            ->whereBetween('earned_at', [$from, $to])
            ->with(['customer:id,name,customer_code', 'payment:id,amount'])
            ->orderByDesc('earned_at')
            ->get()
            ->map(fn (ResellerCommission $row): array => [
                $row->earned_at?->format('Y-m-d') ?? '',
                $row->customer?->customer_code ?? '—',
                $row->customer?->name ?? '—',
                number_format((float) ($row->payment?->amount ?? $row->gross_amount), 2, '.', ''),
                number_format((float) $row->commission_amount, 2, '.', ''),
                (string) $row->status,
            ])
            ->all();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function walletRows(Reseller $reseller, Carbon $from, Carbon $to): array
    {
        $headers = ['Date', 'Type', 'Amount BDT', 'Reference', 'Notes', 'Direction'];

        $rows = ResellerBalanceTransfer::query()
            ->where(function ($q) use ($reseller): void {
                $q->where('to_reseller_id', $reseller->id)->orWhere('from_reseller_id', $reseller->id);
            })
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get()
            ->map(function (ResellerBalanceTransfer $row) use ($reseller): array {
                $incoming = (int) $row->to_reseller_id === (int) $reseller->id;
                $debit = (int) $row->from_reseller_id === (int) $reseller->id && $row->transfer_type === 'debit';

                return [
                    $row->created_at?->format('Y-m-d H:i') ?? '',
                    (string) $row->transfer_type,
                    number_format((float) $row->amount, 2, '.', ''),
                    (string) ($row->reference ?? ''),
                    (string) ($row->notes ?? ''),
                    $debit ? 'Debit' : ($incoming ? 'Credit' : 'Transfer'),
                ];
            })
            ->all();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function dueRows(Reseller $reseller): array
    {
        $headers = ['Code', 'Name', 'Phone', 'Package', 'Status', 'Due BDT', 'Expires'];

        $rows = $reseller->customers()
            ->with('package:id,name')
            ->orderBy('name')
            ->get()
            ->filter(fn (Customer $c): bool => $c->openInvoiceBalance() > 0.009)
            ->map(fn (Customer $c): array => [
                $c->customer_code,
                $c->name,
                (string) ($c->phone ?? ''),
                $c->package?->name ?? '—',
                (string) $c->status,
                number_format($c->openInvoiceBalance(), 2, '.', ''),
                $c->service_expires_at?->format('Y-m-d') ?? '',
            ])
            ->values()
            ->all();

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * @return array{headers: list<string>, rows: list<list<string>>}
     */
    private function clientRows(Reseller $reseller): array
    {
        $headers = ['Code', 'Name', 'Phone', 'Package', 'Status', 'Due BDT', 'Online', 'Joined'];

        $rows = $reseller->customers()
            ->with('package:id,name')
            ->orderBy('name')
            ->get()
            ->map(fn (Customer $c): array => [
                $c->customer_code,
                $c->name,
                (string) ($c->phone ?? ''),
                $c->package?->name ?? '—',
                (string) $c->status,
                number_format($c->openInvoiceBalance(), 2, '.', ''),
                $c->is_ppp_online ? 'Yes' : 'No',
                $c->joined_at?->format('Y-m-d') ?? '',
            ])
            ->all();

        return ['headers' => $headers, 'rows' => $rows];
    }

    private function columnLetter(int $count): string
    {
        $letter = '';
        while ($count > 0) {
            $count--;
            $letter = chr(65 + ($count % 26)).$letter;
            $count = intdiv($count, 26);
        }

        return $letter !== '' ? $letter : 'A';
    }
}
