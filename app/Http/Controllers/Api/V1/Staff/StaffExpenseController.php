<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\CollectorExpense;
use App\Models\User;
use App\Services\Collector\CollectorLedgerQueryService;
use App\Services\Collector\CollectorWalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffExpenseController extends Controller
{
    private const ACCESS_ROLES = [
        'super-admin', 'isp-admin', 'admin', 'cashier', 'collector', 'branch-manager', 'isp-manager',
    ];

    public function categories(CollectorLedgerQueryService $ledger): JsonResponse
    {
        return response()->json([
            'data' => $ledger->expenseCategories()->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'code' => $c->code,
            ]),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $query = CollectorExpense::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->with(['category:id,name', 'collector:id,name'])
            ->orderByDesc('id');

        if (! $user->hasAnyRole(['super-admin', 'isp-admin', 'isp-manager', 'branch-manager'])) {
            $query->where('collector_id', $user->id);
        }

        $expenses = $query->limit(80)->get()->map(fn (CollectorExpense $e) => [
            'id' => $e->id,
            'expense_number' => $e->expense_number,
            'amount' => (float) $e->amount,
            'status' => $e->status,
            'category' => $e->category?->name,
            'collector' => $e->collector?->name,
            'expense_date' => $e->expense_date?->toDateString(),
            'description' => $e->description,
            'created_at' => $e->created_at?->toIso8601String(),
        ]);

        return response()->json(['data' => $expenses]);
    }

    public function store(Request $request, CollectorWalletService $wallet): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['required', 'integer', 'exists:collector_expense_categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'expense_date' => ['nullable', 'date'],
        ]);

        $expense = $wallet->submitExpense(
            collectorId: (int) $user->id,
            amount: (float) $data['amount'],
            categoryId: (int) $data['category_id'],
            description: $data['description'] ?? null,
            expenseDate: $data['expense_date'] ?? null,
        );

        return response()->json(['data' => $expense->load('category')], 201);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->hasAnyRole(self::ACCESS_ROLES), 403);

        return $user;
    }
}
