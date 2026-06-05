<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerProfileService;
use App\Services\Resellers\ResellerCustomerService;
use App\Support\ResellerPortalPermission;
use App\Support\ResellerPortalSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResellerCustomerPackageController extends Controller
{
    public function quote(Request $request, Customer $customer, ResellerCustomerService $customers): JsonResponse
    {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        $packageId = (int) $request->query('package_id');
        if ($packageId <= 0) {
            return response()->json(['message' => 'package_id required'], 422);
        }

        return response()->json(
            app(ResellerCustomerProfileService::class)->packageQuote($reseller, $customer, $packageId),
        );
    }

    public function apply(Request $request, Customer $customer, ResellerCustomerService $customers): RedirectResponse
    {
        if (! app(ResellerPortalSession::class)->canPortal(ResellerPortalPermission::CUSTOMER_EDIT)) {
            abort(403);
        }

        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        $data = $request->validate([
            'package_id' => ['required', 'integer'],
            'confirm_upgrade_invoice' => ['nullable', 'boolean'],
        ]);

        $result = app(ResellerCustomerProfileService::class)->applyPackageChange(
            $reseller,
            $customer,
            (int) $data['package_id'],
            $request->boolean('confirm_upgrade_invoice', true),
        );

        app(\App\Services\Resellers\ResellerPortalActivityLogger::class)->log(
            $reseller,
            'customer.package_change',
            $customer,
            ['package_id' => $data['package_id'], 'scheduled' => $result['scheduled']],
        );

        return redirect()
            ->route('reseller.customers.show', $customer)
            ->with('status', $result['message']);
    }
}
