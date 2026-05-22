<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\CollectorExpense;
use App\Models\StaffExpense;
use App\Models\User;
use App\Services\Collector\CollectorWalletService;
use App\Services\Expenses\StaffExpenseService;
use App\Support\StaffTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffApprovalsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->approver($request);
        $tenantId = StaffTenantScope::tenantIdFor($user);

        $collectorExpenses = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->with(['collector:id,name', 'category:id,name'])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (CollectorExpense $e) => [
                'type' => 'collector_expense',
                'id' => $e->id,
                'number' => $e->expense_number,
                'expense_source' => 'collector',
                'collector' => $e->collector?->name,
                'category' => $e->category?->name,
                'amount' => (float) $e->amount,
                'description' => $e->description,
                'expense_date' => $e->expense_date?->toDateString(),
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        $staffExpenses = StaffExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', StaffExpense::STATUS_PENDING)
            ->with(['submitter:id,name', 'category:id,name', 'vendor:id,name'])
            ->orderByDesc('id')
            ->limit(30)
            ->get()
            ->map(fn (StaffExpense $e) => [
                'type' => 'staff_expense',
                'id' => $e->id,
                'number' => $e->expense_number,
                'expense_source' => $e->expense_source,
                'expense_source_label' => $e->sourceLabel(),
                'submitted_by' => $e->submitter?->name,
                'vendor' => $e->vendor?->name,
                'category' => $e->category?->name,
                'amount' => (float) $e->amount,
                'description' => $e->description,
                'expense_date' => $e->expense_date?->toDateString(),
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        return response()->json([
            'data' => $collectorExpenses->concat($staffExpenses)->sortByDesc('created_at')->values(),
        ]);
    }

    public function approveExpense(Request $request, int $expense, CollectorWalletService $wallet): JsonResponse
    {
        $user = $this->approver($request);
        $model = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($expense)
            ->firstOrFail();

        $wallet->approveExpense($model, $user->id);

        return response()->json(['message' => 'Expense approved.']);
    }

    public function rejectExpense(Request $request, int $expense, CollectorWalletService $wallet): JsonResponse
    {
        $user = $this->approver($request);
        $model = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($expense)
            ->firstOrFail();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $wallet->rejectExpense($model, $data['reason'] ?? 'Rejected from mobile app', $user->id);

        return response()->json(['message' => 'Expense rejected.']);
    }

    public function approveStaffExpense(Request $request, int $expense, StaffExpenseService $service): JsonResponse
    {
        $user = $this->approver($request);
        $model = StaffExpense::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($expense)
            ->firstOrFail();

        $service->approve($model, $user->id);

        return response()->json(['message' => 'Staff expense approved.']);
    }

    public function rejectStaffExpense(Request $request, int $expense, StaffExpenseService $service): JsonResponse
    {
        $user = $this->approver($request);
        $model = StaffExpense::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->whereKey($expense)
            ->firstOrFail();

        $data = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $service->reject($model, $data['reason'] ?? 'Rejected from mobile app', $user->id);

        return response()->json(['message' => 'Staff expense rejected.']);
    }

    private function approver(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        if (! StaffExpenseService::userCanApprove($user)) {
            abort(403, 'Approval not allowed for this role.');
        }

        return $user;
    }
}
