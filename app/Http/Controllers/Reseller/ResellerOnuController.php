<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Portal\CustomerOnuOpticalService;
use App\Services\Resellers\ResellerCustomerService;
use Illuminate\View\View;

class ResellerOnuController extends Controller
{
    public function index(CustomerOnuOpticalService $optical): View
    {
        $reseller = auth('reseller')->user();

        $customers = $reseller->customers()
            ->orderBy('name')
            ->limit(100)
            ->get();

        $rows = $customers->map(function (Customer $customer) use ($optical): array {
            $snap = $optical->snapshot($customer);

            return [
                'customer' => $customer,
                'onu' => $snap,
            ];
        });

        return view('reseller.onu', [
            'reseller' => $reseller,
            'rows' => $rows,
        ]);
    }

    public function show(Customer $customer, CustomerOnuOpticalService $optical, ResellerCustomerService $customers): View
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        return view('reseller.onu-show', [
            'reseller' => $reseller,
            'customer' => $customer,
            'onu' => $optical->snapshot($customer),
        ]);
    }
}
