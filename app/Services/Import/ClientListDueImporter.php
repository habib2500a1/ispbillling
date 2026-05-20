<?php

namespace App\Services\Import;

use App\Models\Customer;
use App\Support\BillingMetricsCache;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use Symfony\Component\Process\Process;

final class ClientListDueImporter
{
    public function __construct(
        private readonly int $tenantId = 1,
    ) {}

    private function applicator(): CustomerDueSnapshotApplicator
    {
        return app(CustomerDueSnapshotApplicator::class, ['tenantId' => $this->tenantId]);
    }

    /**
     * @return array{updated: int, skipped: int, not_found: int, zeroed: int, errors: list<string>}
     */
    public function importFromPath(string $path, bool $dryRun = false): array
    {
        if (! is_readable($path)) {
            throw new RuntimeException("File not readable: {$path}");
        }

        $rows = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'csv', 'txt' => $this->parseCsv($path),
            'xlsx', 'xls' => $this->parseSpreadsheet($path),
            'pdf' => $this->parsePdf($path),
            default => throw new RuntimeException('Supported: csv, xlsx, xls, pdf'),
        };

        $stats = $this->applyRows($rows, $dryRun, basename($path));
        if (! $dryRun) {
            BillingMetricsCache::flush($this->tenantId);
        }

        return $stats;
    }

    /**
     * @param  list<array{customer_code: string, payable: float, paid: float, due: float}>  $rows
     * @return array{updated: int, skipped: int, not_found: int, zeroed: int, errors: list<string>}
     */
    public function applyRows(array $rows, bool $dryRun = false, string $sourceNote = 'Client list import'): array
    {
        $stats = ['updated' => 0, 'skipped' => 0, 'not_found' => 0, 'zeroed' => 0, 'errors' => []];

        foreach ($rows as $i => $row) {
            $code = $this->normalizeCode($row['customer_code'] ?? '');
            if ($code === '') {
                $stats['skipped']++;

                continue;
            }

            $customer = Customer::withoutGlobalScopes()
                ->where('tenant_id', $this->tenantId)
                ->where('customer_code', $code)
                ->first();

            if ($customer === null) {
                $stats['not_found']++;
                $stats['errors'][] = 'Line '.($i + 1).": customer {$code} not found";

                continue;
            }

            $payable = round((float) ($row['payable'] ?? 0), 2);
            $paid = round((float) ($row['paid'] ?? 0), 2);
            $due = round((float) ($row['due'] ?? 0), 2);

            if ($due <= 0.009 && $payable <= 0.009) {
                $stats['zeroed']++;
                if (! $dryRun) {
                    $this->applicator()->apply($customer, 0, 0, 0, sourceNote: $sourceNote);
                }
                $stats['updated']++;

                continue;
            }

            if ($due <= 0.009 && $payable > 0) {
                $due = max(0, $payable - $paid);
            }

            if (! $dryRun) {
                $this->applicator()->apply($customer, $payable, $paid, $due, sourceNote: $sourceNote);
            }

            $stats['updated']++;
        }

        return $stats;
    }

    /**
     * @return list<array{customer_code: string, payable: float, paid: float, due: float}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException('Cannot open CSV');
        }

        $header = null;
        $rows = [];
        while (($line = fgetcsv($handle)) !== false) {
            if ($line === [null] || $line === []) {
                continue;
            }
            if ($header === null) {
                $header = $this->mapHeaders($line);
                if ($header['customer_code'] === null) {
                    $header = $this->guessColumnIndexes($line);
                }

                continue;
            }

            $parsed = $this->rowFromIndexes($line, $header);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }
        fclose($handle);

        return $rows;
    }

    /**
     * @return list<array{customer_code: string, payable: float, paid: float, due: float}>
     */
    private function parseSpreadsheet(string $path): array
    {
        $sheet = IOFactory::load($path)->getActiveSheet();
        $matrix = $sheet->toArray();
        if ($matrix === []) {
            return [];
        }

        $headerRow = (array) ($matrix[0] ?? []);
        $header = $this->mapHeaders($headerRow);
        if ($header['customer_code'] === null) {
            $header = $this->guessColumnIndexes($headerRow);
        }

        $rows = [];
        foreach (array_slice($matrix, 1) as $line) {
            $parsed = $this->rowFromIndexes((array) $line, $header);
            if ($parsed !== null) {
                $rows[] = $parsed;
            }
        }

        return $rows;
    }

    /**
     * @return list<array{customer_code: string, payable: float, paid: float, due: float}>
     */
    private function parsePdf(string $path): array
    {
        $process = new Process(['pdftotext', '-layout', $path, '-']);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new RuntimeException('pdftotext failed: '.$process->getErrorOutput());
        }

        $text = $process->getOutput();
        $rows = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '' || stripos($line, 'client') !== false && stripos($line, 'due') !== false) {
                continue;
            }

            if (! preg_match('/^(\d{1,6})\b/', $line, $codeMatch)) {
                continue;
            }

            if (! preg_match_all('/(\d{1,3}(?:,\d{3})*(?:\.\d{2})?|\d+\.\d{2})/', $line, $amounts)) {
                continue;
            }

            $nums = array_map(fn (string $a): float => $this->parseMoney($a), $amounts[1]);
            $nums = array_values(array_filter($nums, fn (float $n): bool => $n >= 0));

            if (count($nums) < 1) {
                continue;
            }

            $due = (float) (array_pop($nums) ?? 0);
            $paid = count($nums) > 0 ? (float) array_pop($nums) : 0.0;
            $payable = count($nums) > 0 ? (float) array_pop($nums) : ($due + $paid);

            $rows[] = [
                'customer_code' => $codeMatch[1],
                'payable' => $payable,
                'paid' => $paid,
                'due' => $due,
            ];
        }

        if ($rows === []) {
            throw new RuntimeException('No client rows parsed from PDF. Export CSV/XLSX from ISP Digital or upload a clearer PDF.');
        }

        return $rows;
    }

    /**
     * @param  list<string|null>  $headerRow
     * @return array{customer_code: int|null, payable: int|null, paid: int|null, due: int|null}
     */
    private function mapHeaders(array $headerRow): array
    {
        $map = ['customer_code' => null, 'payable' => null, 'paid' => null, 'due' => null];

        foreach ($headerRow as $idx => $cell) {
            $key = $this->headerKey((string) $cell);
            if ($key !== null && $map[$key] === null) {
                $map[$key] = (int) $idx;
            }
        }

        return $map;
    }

    /**
     * @param  list<mixed>  $headerRow
     * @return array{customer_code: int|null, payable: int|null, paid: int|null, due: int|null}
     */
    private function guessColumnIndexes(array $headerRow): array
    {
        return [
            'customer_code' => 0,
            'payable' => null,
            'paid' => null,
            'due' => null,
        ];
    }

    private function headerKey(string $label): ?string
    {
        $n = Str::lower(trim(preg_replace('/\s+/', ' ', $label) ?? ''));

        return match (true) {
            str_contains($n, 'client') && (str_contains($n, 'id') || str_contains($n, 'code')) => 'customer_code',
            $n === 'id', $n === 'code', $n === 'customer id' => 'customer_code',
            str_contains($n, 'monthly') && str_contains($n, 'bill') => 'payable',
            str_contains($n, 'payabale'), str_contains($n, 'payable') => 'payable',
            str_contains($n, 'received'), str_contains($n, 'paid amount'), $n === 'paid' => 'paid',
            str_contains($n, 'balance due'), str_contains($n, 'due') => 'due',
            default => null,
        };
    }

    /**
     * @param  list<mixed>  $line
     * @param  array{customer_code: int|null, payable: int|null, paid: int|null, due: int|null}  $header
     * @return array{customer_code: string, payable: float, paid: float, due: float}|null
     */
    private function rowFromIndexes(array $line, array $header): ?array
    {
        $codeIdx = $header['customer_code'] ?? 0;
        $code = $this->normalizeCode((string) ($line[$codeIdx] ?? ''));
        if ($code === '') {
            return null;
        }

        $get = fn (?int $idx): float => $idx === null ? 0.0 : $this->parseMoney($line[$idx] ?? 0);

        $payable = $get($header['payable']);
        $paid = $get($header['paid']);
        $due = $get($header['due']);

        if ($due <= 0 && $header['due'] === null && $payable > 0) {
            $due = max(0, $payable - $paid);
        }

        return [
            'customer_code' => $code,
            'payable' => $payable,
            'paid' => $paid,
            'due' => $due,
        ];
    }

    private function normalizeCode(string $code): string
    {
        $code = trim($code);
        if (preg_match('/^(\d+)/', $code, $m)) {
            return $m[1];
        }

        return $code;
    }

    private function parseMoney(mixed $value): float
    {
        if (is_numeric($value)) {
            return round((float) $value, 2);
        }

        $clean = preg_replace('/[^\d.]/', '', (string) $value) ?? '';

        return round((float) ($clean !== '' ? $clean : 0), 2);
    }
}
