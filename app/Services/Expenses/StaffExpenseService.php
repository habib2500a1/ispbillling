<?php

namespace App\Services\Expenses;

use App\Models\StaffExpense;
use App\Models\StaffExpenseCategory;
use App\Models\User;
use App\Models\VendorPayment;
use App\Support\TenantResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class StaffExpenseService
{
    public function ensureDefaultCategories(int $tenantId): void
    {
        $sort = 0;
        foreach (config('staff_expenses.categories', []) as $source => $categories) {
            foreach ($categories as $code => $name) {
                StaffExpenseCategory::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'code' => $code],
                    [
                        'name' => $name,
                        'expense_source' => $source,
                        'is_active' => true,
                        'sort_order' => $sort++,
                    ],
                );
            }
        }
    }

    /**
     * @param  array{
     *   expense_source: string,
     *   amount: float,
     *   category_id: int,
     *   expense_date?: string|null,
     *   description?: string|null,
     *   proof_path?: string|null,
     *   vendor_id?: int|null,
     *   payment_method?: string,
     * }  $data
     */
    public function submit(array $data, ?User $submitter = null): StaffExpense
    {
        $submitter ??= auth()->user();
        if (! $submitter instanceof User) {
            throw ValidationException::withMessages(['user' => 'You must be logged in to submit an expense.']);
        }

        $amount = round((float) $data['amount'], 2);
        if ($amount < 0.01) {
            throw ValidationException::withMessages(['amount' => 'Amount must be at least 0.01 BDT.']);
        }

        $source = (string) $data['expense_source'];
        if (! array_key_exists($source, config('staff_expenses.sources', []))) {
            throw ValidationException::withMessages(['expense_source' => 'Invalid expense type.']);
        }

        if ($source === StaffExpense::SOURCE_VENDOR && empty($data['vendor_id'])) {
            throw ValidationException::withMessages(['vendor_id' => 'Select a vendor for vendor expenses.']);
        }

        $tenantId = TenantResolver::requiredTenantId();
        $this->ensureDefaultCategories($tenantId);

        $requiresApproval = (bool) config('staff_expenses.requires_approval', true);
        $now = now();

        return StaffExpense::query()->create([
            'tenant_id' => $tenantId,
            'expense_number' => StaffExpense::generateNumber($tenantId),
            'expense_source' => $source,
            'vendor_id' => $data['vendor_id'] ?? null,
            'category_id' => (int) $data['category_id'],
            'amount' => $amount,
            'payment_method' => $data['payment_method'] ?? 'cash',
            'status' => $requiresApproval ? StaffExpense::STATUS_PENDING : StaffExpense::STATUS_APPROVED,
            'expense_date' => $data['expense_date'] ?? $now->toDateString(),
            'description' => $data['description'] ?? null,
            'proof_path' => $data['proof_path'] ?? null,
            'submitted_by' => $submitter->id,
            'branch_id' => $submitter->branch_id,
            'approved_by' => $requiresApproval ? null : $submitter->id,
            'approved_at' => $requiresApproval ? null : $now,
        ]);
    }

    public function approve(StaffExpense $expense, ?int $approvedBy = null): StaffExpense
    {
        if ($expense->status !== StaffExpense::STATUS_PENDING) {
            throw ValidationException::withMessages(['expense' => 'Only pending expenses can be approved.']);
        }

        return DB::transaction(function () use ($expense, $approvedBy): StaffExpense {
            $meta = is_array($expense->meta) ? $expense->meta : [];

            if ($expense->expense_source === StaffExpense::SOURCE_VENDOR && $expense->vendor_id) {
                $payment = VendorPayment::query()->create([
                    'tenant_id' => $expense->tenant_id,
                    'vendor_id' => $expense->vendor_id,
                    'payment_date' => $expense->expense_date,
                    'amount' => $expense->amount,
                    'vat_amount' => 0,
                    'payment_method' => $this->mapPaymentMethodForVendor($expense->payment_method),
                    'reference' => $expense->expense_number,
                    'status' => 'completed',
                    'notes' => trim('Staff expense approval: '.($expense->description ?? '')),
                ]);
                $meta['vendor_payment_id'] = $payment->id;
            }

            $expense->forceFill([
                'status' => StaffExpense::STATUS_APPROVED,
                'approved_by' => $approvedBy ?? auth()->id(),
                'approved_at' => now(),
                'meta' => $meta,
            ])->save();

            return $expense->fresh(['category', 'vendor', 'submitter', 'approver']);
        });
    }

    public function reject(StaffExpense $expense, string $reason, ?int $rejectedBy = null): StaffExpense
    {
        if ($expense->status !== StaffExpense::STATUS_PENDING) {
            throw ValidationException::withMessages(['expense' => 'Only pending expenses can be rejected.']);
        }

        $expense->forceFill([
            'status' => StaffExpense::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'rejected_at' => now(),
            'approved_by' => $rejectedBy ?? auth()->id(),
        ])->save();

        return $expense->fresh(['category', 'vendor', 'submitter']);
    }

    public static function userCanApprove(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (\App\Support\Rbac\StaffCapability::for($user)->isTenantAdmin()) {
            return true;
        }

        if ($user->hasAnyRole(['isp-manager', 'branch-manager'])) {
            return true;
        }

        return $user->can('accounting.manage')
            || $user->can('accounting.view');
    }

    public static function userCanSubmit(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        if (\App\Support\Rbac\StaffCapability::for($user)->isTenantAdmin()) {
            return true;
        }

        if (static::userCanApprove($user)) {
            return true;
        }

        return $user->hasAnyRole([
            'cashier', 'collector', 'isp-manager', 'branch-manager',
        ]) || $user->can('payments.add')
            || $user->can('billing.view')
            || $user->can('collections.view');
    }

    private function mapPaymentMethodForVendor(string $method): string
    {
        return match ($method) {
            'bank', 'cheque' => 'bank',
            default => 'cash',
        };
    }
}
