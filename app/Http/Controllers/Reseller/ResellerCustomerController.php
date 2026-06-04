<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerCustomerController extends Controller
{
    public function index(Request $request): View
    {
        /** @var \App\Models\Reseller $reseller */
        $reseller = auth('reseller')->user();
        $search = trim((string) $request->query('q', ''));
        $dueOnly = $request->boolean('due');

        $customers = Customer::query()
            ->where('reseller_id', $reseller->id)
            ->with(['package:id,name', 'zone:id,name'])
            ->when($dueOnly, function ($q): void {
                $q->whereHas('invoices', function ($iq): void {
                    $iq->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
                        ->whereRaw('(total - amount_paid) > 0.009');
                });
            })
            ->when($search !== '', function ($q) use ($search): void {
                $q->where(function ($inner) use ($search): void {
                    $inner->where('name', 'like', "%{$search}%")
                        ->orWhere('customer_code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        $customerIds = $reseller->customers()->pluck('id');
        $dueCustomerCount = 0;
        $totalDue = 0.0;
        if ($customerIds->isNotEmpty()) {
            $dueCustomerCount = (int) Customer::query()
                ->whereIn('id', $customerIds)
                ->whereHas('invoices', function ($iq): void {
                    $iq->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
                        ->whereRaw('(total - amount_paid) > 0.009');
                })
                ->count();

            $totalDue = (float) \App\Models\Invoice::query()
                ->whereIn('customer_id', $customerIds)
                ->whereIn('status', ['open', 'partial', 'sent', 'overdue'])
                ->get(['total', 'amount_paid'])
                ->sum(fn ($inv) => max(0, (float) $inv->total - (float) $inv->amount_paid));
        }

        return view('reseller.customers', [
            'reseller' => $reseller,
            'customers' => $customers,
            'search' => $search,
            'dueOnly' => $dueOnly,
            'dueCustomerCount' => $dueCustomerCount,
            'totalDue' => round($totalDue, 2),
        ]);
    }
}
