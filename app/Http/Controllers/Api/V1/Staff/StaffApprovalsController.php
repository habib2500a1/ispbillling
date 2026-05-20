<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\CollectorExpense;
use App\Models\User;
use App\Services\Collector\CollectorWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffApprovalsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $this->approver($request);
        $tenantId = (int) $user->tenant_id;

        $expenses = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'pending')
            ->with(['collector:id,name', 'category:id,name'])
            ->orderByDesc('id')
            ->limit(50)
            ->get()
            ->map(fn (CollectorExpense $e) => [
                'type' => 'expense',
                'id' => $e->id,
                'number' => $e->expense_number,
                'collector' => $e->collector?->name,
                'category' => $e->category?->name,
                'amount' => (float) $e->amount,
                'description' => $e->description,
                'expense_date' => $e->expense_date?->toDateString(),
                'created_at' => $e->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $expenses->values()]);
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

    private function approver(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(401);
        }
        if (! $user->hasAnyRole(['super-admin', 'isp-admin', 'admin', 'isp-manager', 'branch-manager'])) {
            abort(403, 'Approval not allowed for this role.');
        }

        return $user;
    }
}
