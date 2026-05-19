<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerCommission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerCommissionController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $status = (string) $request->query('status', '');

        $commissions = ResellerCommission::query()
            ->where('reseller_id', $reseller->id)
            ->with(['customer:id,name,customer_code', 'payment:id,amount,paid_at,method'])
            ->when(in_array($status, ['pending', 'paid', 'cancelled'], true), fn ($q) => $q->where('status', $status))
            ->latest('earned_at')
            ->paginate(25)
            ->withQueryString();

        $totals = [
            'pending' => (float) ResellerCommission::query()
                ->where('reseller_id', $reseller->id)
                ->where('status', ResellerCommission::STATUS_PENDING)
                ->sum('commission_amount'),
            'paid' => (float) ResellerCommission::query()
                ->where('reseller_id', $reseller->id)
                ->where('status', ResellerCommission::STATUS_PAID)
                ->sum('commission_amount'),
        ];

        return view('reseller.commissions', [
            'reseller' => $reseller,
            'commissions' => $commissions,
            'status' => $status,
            'totals' => $totals,
        ]);
    }
}
