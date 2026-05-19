<?php

namespace App\Services\Accounting;

use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LedgerService
{
    public function __construct(
        private readonly ChartOfAccountSeeder $chartSeeder,
    ) {}

    public function accountByCode(string $code, ?int $tenantId = null): ChartOfAccount
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $account = ChartOfAccount::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('code', $code)
            ->first();

        if (! $account) {
            $this->chartSeeder->seedForTenant($tenantId);
            $account = ChartOfAccount::withoutGlobalScopes()
                ->where('tenant_id', $tenantId)
                ->where('code', $code)
                ->first();
        }

        if (! $account) {
            throw new InvalidArgumentException("Chart account [{$code}] not found for tenant {$tenantId}.");
        }

        return $account;
    }

    /**
     * @param  list<array{account_id?: int, account_code?: string, debit?: float|int|string, credit?: float|int|string, bank_account_id?: int|null, description?: string|null}>  $lines
     */
    public function post(
        string $description,
        array $lines,
        ?\DateTimeInterface $date = null,
        string $sourceType = 'manual',
        ?int $sourceId = null,
        ?int $tenantId = null,
    ): JournalEntry {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $date = $date ?? now();

        $normalized = [];
        $totalDebit = 0.0;
        $totalCredit = 0.0;

        foreach ($lines as $line) {
            $debit = round((float) ($line['debit'] ?? 0), 2);
            $credit = round((float) ($line['credit'] ?? 0), 2);
            if ($debit <= 0 && $credit <= 0) {
                throw new InvalidArgumentException('Each line must have a debit or credit amount.');
            }
            $accountId = $line['account_id'] ?? null;
            if (! $accountId && ! empty($line['account_code'])) {
                $accountId = $this->accountByCode((string) $line['account_code'], $tenantId)->id;
            }
            if (! $accountId) {
                throw new InvalidArgumentException('Each line requires account_id or account_code.');
            }
            $normalized[] = [
                'chart_account_id' => $accountId,
                'bank_account_id' => $line['bank_account_id'] ?? null,
                'debit' => $debit,
                'credit' => $credit,
                'line_description' => $line['description'] ?? null,
            ];
            $totalDebit += $debit;
            $totalCredit += $credit;
        }

        if (abs($totalDebit - $totalCredit) > 0.01) {
            throw new InvalidArgumentException(
                sprintf('Journal not balanced: debit %.2f vs credit %.2f', $totalDebit, $totalCredit)
            );
        }

        return DB::transaction(function () use ($description, $normalized, $date, $sourceType, $sourceId, $tenantId) {
            $entry = JournalEntry::create([
                'tenant_id' => $tenantId,
                'entry_date' => $date,
                'description' => $description,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'status' => 'posted',
                'posted_at' => now(),
                'created_by' => auth()->id(),
            ]);

            foreach ($normalized as $row) {
                JournalEntryLine::create([
                    'journal_entry_id' => $entry->id,
                    ...$row,
                ]);
                if ($row['bank_account_id']) {
                    $this->adjustBankBalance((int) $row['bank_account_id'], $row['debit'], $row['credit']);
                }
            }

            return $entry->load('lines.chartAccount');
        });
    }

    private function adjustBankBalance(int $bankAccountId, float $debit, float $credit): void
    {
        $bank = BankAccount::find($bankAccountId);
        if (! $bank) {
            return;
        }
        $delta = $debit - $credit;
        $bank->increment('current_balance', $delta);
    }

    public function accountBalance(int $accountId, ?\DateTimeInterface $asOf = null): float
    {
        $account = ChartOfAccount::findOrFail($accountId);
        $query = JournalEntryLine::query()
            ->where('chart_account_id', $accountId)
            ->whereHas('journalEntry', function ($q) use ($asOf) {
                $q->where('status', 'posted');
                if ($asOf) {
                    $q->whereDate('entry_date', '<=', $asOf);
                }
            });

        $debit = (float) $query->clone()->sum('debit');
        $credit = (float) $query->clone()->sum('credit');

        if ($account->isDebitNormal()) {
            return round($debit - $credit, 2);
        }

        return round($credit - $debit, 2);
    }
}
