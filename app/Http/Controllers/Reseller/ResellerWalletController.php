<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\ResellerBalanceTransfer;
use Illuminate\View\View;

class ResellerWalletController extends Controller
{
    public function index(): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();

        $transfers = ResellerBalanceTransfer::query()
            ->where(function ($q) use ($reseller): void {
                $q->where('to_reseller_id', $reseller->id)
                    ->orWhere('from_reseller_id', $reseller->id);
            })
            ->latest()
            ->limit(50)
            ->get();

        return view('reseller.wallet', [
            'reseller' => $reseller,
            'transfers' => $transfers,
        ]);
    }
}
