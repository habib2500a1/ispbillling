<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerSettlement;
use App\Services\Resellers\ResellerSettlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerSettlementController extends Controller
{
    public function index(ResellerSettlementService $settlements): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        return view('reseller.settlements', [
            'reseller' => $reseller,
            'outstanding' => $settlements->outstandingBalance($reseller),
            'rows' => $reseller->settlements()->latest('submitted_at')->paginate(20),
        ]);
    }

    public function store(Request $request, ResellerSettlementService $settlements): RedirectResponse
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:1'],
            'expense_deduction' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payment_method' => ['nullable', 'string', 'max:32'],
            'reference' => ['nullable', 'string', 'max:128'],
        ]);

        $settlements->submitRequest(
            $reseller,
            (float) $validated['amount'],
            $validated['notes'] ?? null,
            (float) ($validated['expense_deduction'] ?? 0),
            $validated['payment_method'] ?? null,
            $validated['reference'] ?? null,
        );

        return back()->with('status', 'Settlement request submitted for admin approval.');
    }
}
