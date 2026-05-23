<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Portal\CustomerOnuOpticalService;
use App\Services\Resellers\ResellerCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiOnuController extends Controller
{
    public function index(Request $request, CustomerOnuOpticalService $optical): JsonResponse
    {
        $reseller = $request->user();

        $data = $reseller->customers()
            ->orderBy('name')
            ->limit(100)
            ->get()
            ->map(fn (Customer $c) => [
                'customer_id' => $c->id,
                'customer_code' => $c->customer_code,
                'name' => $c->name,
                'onu' => $optical->snapshot($c),
            ]);

        return response()->json(['rows' => $data]);
    }

    public function show(Request $request, Customer $customer, CustomerOnuOpticalService $optical, ResellerCustomerService $customers): JsonResponse
    {
        $customers->assertOwned($request->user(), $customer);

        return response()->json(['onu' => $optical->snapshot($customer)]);
    }
}
