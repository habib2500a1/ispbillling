<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerLifecycleService;
use App\Services\Resellers\ResellerCustomerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ResellerCustomerActionController extends Controller
{
    public function renew(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle, ResellerCustomerService $customers): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $result = $lifecycle->renew($reseller, $customer, $request);

        return back()->with('status', $result['message']);
    }

    public function suspend(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $result = $lifecycle->suspend($reseller, $customer, $request);

        return back()->with('status', $result['message']);
    }

    public function reconnect(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $result = $lifecycle->reconnect($reseller, $customer, $request);

        return back()->with('status', $result['message']);
    }

    public function changePassword(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $result = $lifecycle->changePassword($reseller, $customer, $request);

        return back()->with('status', $result['message']);
    }
}
