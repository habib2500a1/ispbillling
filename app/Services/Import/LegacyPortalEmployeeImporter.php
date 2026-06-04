<?php

namespace App\Services\Import;

use App\Models\Employee;
use Carbon\Carbon;

final class LegacyPortalEmployeeImporter
{
    public function __construct(
        private readonly int $tenantId = 1,
    ) {}

    /**
     * @return array{imported: int, updated: int, skipped: int}
     */
    public function importAll(LegacyPortalSessionClient $client, bool $force = false): array
    {
        $stats = ['imported' => 0, 'updated' => 0, 'skipped' => 0];
        $start = 0;
        $length = 500;

        do {
            $page = $client->fetchEmployeesPage($start, $length);
            $rows = $page['aaData'];
            $total = $page['iTotalDisplayRecords'];

            foreach ($rows as $row) {
                $result = $this->importRow($row, $force);
                $stats[$result]++;
            }

            $start += $length;
        } while ($start < $total && $rows !== []);

        return $stats;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return 'imported'|'updated'|'skipped'
     */
    public function importRow(array $row, bool $force = false): string
    {
        $code = trim((string) ($row['EmpId'] ?? ''));
        if ($code === '') {
            $code = 'ISD-EMP-'.(int) ($row['EmpHeaderId'] ?? 0);
        }

        $existing = Employee::query()
            ->where('tenant_id', $this->tenantId)
            ->where('employee_code', $code)
            ->first();

        if ($existing !== null && ! $force) {
            return 'skipped';
        }

        $name = trim((string) ($row['EmpName'] ?? ''));
        if ($name === '') {
            return 'skipped';
        }

        $attrs = [
            'tenant_id' => $this->tenantId,
            'employee_code' => $code,
            'name' => $name,
            'designation' => trim((string) (($row['Designation'] ?? '') ?: ($row['PositionName'] ?? ''))),
            'department' => trim((string) ($row['Department'] ?? '')),
            'join_date' => $this->parseJoinDate($row['JoiningDate'] ?? null),
            'phone' => trim((string) (($row['MobileNumber'] ?? '') ?: ($row['PhoneNumber'] ?? ''))),
            'email' => filled($row['Email'] ?? null) ? trim((string) $row['Email']) : null,
            'base_salary' => (float) ($row['EmpSalary'] ?? $row['EmployeeSalary'] ?? 0),
            'is_active' => strtolower((string) ($row['Status'] ?? 'active')) !== 'inactive',
        ];

        if ($existing !== null) {
            $existing->forceFill($attrs)->saveQuietly();

            return 'updated';
        }

        Employee::query()->create($attrs + [
            'wallet_balance' => 0,
        ]);

        return 'imported';
    }

    private function parseJoinDate(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('/\/Date\((-?\d+)\)\//', $value, $m)) {
            return null;
        }

        $ms = (int) $m[1];
        if ($ms < 1) {
            return null;
        }

        return Carbon::createFromTimestampMs($ms)->toDateString();
    }
}
