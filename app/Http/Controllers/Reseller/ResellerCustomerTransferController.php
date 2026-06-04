<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Reseller;
use App\Models\ResellerCustomerTransfer;
use App\Services\Resellers\ResellerCustomerTransferService;
use App\Services\Resellers\ResellerHierarchyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerCustomerTransferController extends Controller
{
    public function index(): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();

        $transfers = ResellerCustomerTransfer::query()
            ->where(function ($q) use ($reseller): void {
                $q->where('from_reseller_id', $reseller->id)
                    ->orWhere('to_reseller_id', $reseller->id);
            })
            ->with(['customer:id,name,customer_code', 'fromReseller:id,name,code', 'toReseller:id,name,code'])
            ->latest()
            ->limit(50)
            ->get();

        return view('reseller.enterprise.customer-transfers', [
            'reseller' => $reseller,
            'transfers' => $transfers,
        ]);
    }

    public function create(Customer $customer, ResellerHierarchyService $hierarchy): View
    {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless((int) $customer->reseller_id === (int) $reseller->id, 404);

        $targets = $hierarchy->descendants($reseller)
            ->merge($reseller->children)
            ->unique('id')
            ->values();

        return view('reseller.enterprise.customer-transfer-form', [
            'reseller' => $reseller,
            'customer' => $customer,
            'targets' => $targets,
        ]);
    }

    public function store(
        Request $request,
        Customer $customer,
        ResellerCustomerTransferService $transfers,
    ): RedirectResponse {
        /** @var Reseller $reseller */
        $reseller = auth('reseller')->user();
        abort_unless((int) $customer->reseller_id === (int) $reseller->id, 404);

        $validated = $request->validate([
            'to_reseller_id' => ['required', 'integer', 'exists:resellers,id'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $to = Reseller::query()->findOrFail((int) $validated['to_reseller_id']);
        abort_unless(
            (int) $to->parent_id === (int) $reseller->id || app(ResellerHierarchyService::class)->isDescendantOf($to, $reseller),
            403,
        );

        $transfers->request(
            $customer,
            $reseller,
            $to,
            $reseller,
            $validated['reason'] ?? null,
            config('reseller_enterprise.transfers.require_admin_approval', true),
        );

        return redirect()
            ->route('reseller.customer-transfers.index')
            ->with('status', 'Transfer request submitted.');
    }
}
