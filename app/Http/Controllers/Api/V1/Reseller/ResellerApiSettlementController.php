<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerSettlement;
use App\Services\Resellers\ResellerSettlementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiSettlementController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $rows = ResellerSettlement::query()
            ->where('reseller_id', $reseller->id)
            ->latest('submitted_at')
            ->paginate(min(30, (int) $request->query('per_page', 15)));

        return response()->json([
            'outstanding_balance' => app(ResellerSettlementService::class)->outstandingBalance($reseller),
            'settlements' => $rows,
        ]);
    }

    public function store(Request $request, ResellerSettlementService $settlements): JsonResponse
    {
        $reseller = $request->user();
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'expense_deduction' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);

        $settlement = $settlements->submitRequest(
            $reseller,
            (float) $data['amount'],
            $data['notes'] ?? null,
            (float) ($data['expense_deduction'] ?? 0),
            $data['payment_method'] ?? null,
            $data['reference'] ?? null,
        );

        app(\App\Services\Resellers\ResellerPortalNotifier::class)->settlementSubmitted(
            $reseller,
            (float) $settlement->net_amount,
            $settlement->settlement_number,
        );

        return response()->json(['settlement' => $settlement, 'message' => 'Settlement request submitted.'], 201);
    }
}
