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

        $customers = Customer::query()
            ->where('reseller_id', $reseller->id)
            ->with(['package:id,name', 'zone:id,name'])
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

        return view('reseller.customers', [
            'reseller' => $reseller,
            'customers' => $customers,
            'search' => $search,
        ]);
    }
}
