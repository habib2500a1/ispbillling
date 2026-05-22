<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\StaffExpense;
use App\Models\StaffExpenseCategory;
use App\Models\User;
use App\Services\Expenses\StaffExpenseService;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffExpenseController extends Controller
{
    public function categories(StaffExpenseService $service): JsonResponse
    {
        $tenantId = TenantResolver::requiredTenantId();
        $service->ensureDefaultCategories($tenantId);

        $categories = StaffExpenseCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'name', 'code', 'expense_source']);

        return response()->json([
            'data' => $categories,
            'sources' => config('staff_expenses.sources', []),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->user($request);
        $query = StaffExpense::withoutGlobalScopes()
            ->where('tenant_id', $user->tenant_id)
            ->with(['category:id,name', 'vendor:id,name', 'submitter:id,name'])
            ->orderByDesc('id');

        if (! StaffExpenseService::userCanApprove($user)) {
            $query->where('submitted_by', $user->id);
        }

        $expenses = $query->limit(80)->get()->map(fn (StaffExpense $e) => $this->serialize($e));

        return response()->json(['data' => $expenses]);
    }

    public function store(Request $request, StaffExpenseService $service): JsonResponse
    {
        $user = $this->user($request);

        $data = $request->validate([
            'expense_source' => ['required', 'string', 'in:vendor,office,other'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_id' => ['required', 'integer', 'exists:staff_expense_categories,id'],
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'description' => ['nullable', 'string', 'max:1000'],
            'expense_date' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', 'max:24'],
        ]);

        $expense = $service->submit([
            ...$data,
            'amount' => (float) $data['amount'],
        ], $user);

        return response()->json(['data' => $this->serialize($expense->load(['category', 'vendor']))], 201);
    }

    private function serialize(StaffExpense $e): array
    {
        return [
            'id' => $e->id,
            'expense_number' => $e->expense_number,
            'expense_source' => $e->expense_source,
            'expense_source_label' => $e->sourceLabel(),
            'amount' => (float) $e->amount,
            'status' => $e->status,
            'category' => $e->category?->name,
            'vendor' => $e->vendor?->name,
            'payment_method' => $e->payment_method,
            'submitted_by' => $e->submitter?->name,
            'expense_date' => $e->expense_date?->toDateString(),
            'description' => $e->description,
            'created_at' => $e->created_at?->toIso8601String(),
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && StaffExpenseService::userCanSubmit($user), 403);

        return $user;
    }
}
