<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\ResellerCustomerTransfer;
use App\Services\Resellers\ResellerCustomerTransferService;
use App\Services\Resellers\ResellerHierarchyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiCustomerTransferController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();

        $transfers = ResellerCustomerTransfer::query()
            ->where(function ($q) use ($reseller): void {
                $q->where('from_reseller_id', $reseller->id)
                    ->orWhere('to_reseller_id', $reseller->id);
            })
            ->with(['customer:id,name,customer_code', 'fromReseller:id,name,code', 'toReseller:id,name,code'])
            ->latest()
            ->limit(50)
            ->get();

        return response()->json(['transfers' => $transfers]);
    }

    public function store(
        Request $request,
        Customer $customer,
        ResellerCustomerTransferService $transfers,
        ResellerHierarchyService $hierarchy,
    ): JsonResponse {
        $reseller = $request->user();
        abort_unless((int) $customer->reseller_id === (int) $reseller->id, 404);

        $validated = $request->validate([
            'to_reseller_id' => ['required', 'integer', 'exists:resellers,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $to = Reseller::query()->findOrFail((int) $validated['to_reseller_id']);
        abort_unless(
            (int) $to->parent_id === (int) $reseller->id || $hierarchy->isDescendantOf($to, $reseller),
            403,
        );

        $transfer = $transfers->request(
            $customer,
            $reseller,
            $to,
            $reseller,
            $validated['reason'] ?? null,
            config('reseller_enterprise.transfers.require_admin_approval', true),
        );

        return response()->json([
            'transfer' => $transfer->load(['customer:id,name,customer_code', 'fromReseller:id,code', 'toReseller:id,code']),
            'message' => 'Transfer request submitted.',
        ], 201);
    }
}
