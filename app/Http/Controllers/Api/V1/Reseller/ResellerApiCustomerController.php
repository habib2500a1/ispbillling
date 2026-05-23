<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiCustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $reseller = $request->user();
        $search = trim((string) $request->query('q', ''));

        $rows = Customer::query()
            ->where('reseller_id', $reseller->id)
            ->when($search !== '', fn ($q) => $q->where(function ($inner) use ($search): void {
                $inner->where('name', 'like', "%{$search}%")
                    ->orWhere('customer_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            }))
            ->with('package:id,name,price_monthly')
            ->orderBy('name')
            ->paginate(min(50, (int) $request->query('per_page', 20)));

        return response()->json($rows);
    }

    public function store(Request $request, ResellerCustomerService $service): JsonResponse
    {
        $result = $service->create($request->user(), $request);

        return response()->json($result, 201);
    }

    public function show(Request $request, Customer $customer, ResellerCustomerService $service): JsonResponse
    {
        $service->assertOwned($request->user(), $customer);

        return response()->json($customer->load('package:id,name'));
    }

    public function update(Request $request, Customer $customer, ResellerCustomerService $service): JsonResponse
    {
        $updated = $service->update($request->user(), $customer, $request);

        return response()->json(['customer' => $updated]);
    }
}
