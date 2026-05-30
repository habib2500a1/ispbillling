<?php

namespace App\Http\Controllers\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerLifecycleService;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerNetworkSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ResellerNetworkController extends Controller
{
    public function index(
        Request $request,
        ResellerNetworkSessionService $sessions,
    ): View {
        $reseller = auth('reseller')->user();
        $filter = $request->input('filter', 'online');

        $query = $reseller->customers()->with([
            'package:id,name',
            'activePppSession.mikrotikServer:id,name',
            'lastEndedPppSession:id,customer_id,ended_at',
        ]);

        if ($filter === 'online') {
            $query->where('is_ppp_online', true);
        } elseif ($filter === 'offline') {
            $query->where('is_ppp_online', false)->where('status', 'active');
        }

        $clients = $query->orderBy('name')->paginate(25)->withQueryString();
        $sessionMap = $sessions->summarizeMany($clients->getCollection(), refreshLiveForOnline: $filter === 'online');

        return view('reseller.network.index', [
            'reseller' => $reseller,
            'clients' => $clients,
            'sessionMap' => $sessionMap,
            'filter' => $filter,
            'onlineCount' => $reseller->customers()->where('is_ppp_online', true)->count(),
            'offlineCount' => $reseller->customers()->where('is_ppp_online', false)->where('status', 'active')->count(),
        ]);
    }

    public function session(
        Customer $customer,
        ResellerCustomerService $customers,
        ResellerNetworkSessionService $sessions,
    ): JsonResponse {
        $reseller = auth('reseller')->user();
        $customers->assertOwned($reseller, $customer);

        return response()->json($sessions->liveDetail($customer));
    }

    public function disconnect(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): RedirectResponse
    {
        $reseller = auth('reseller')->user();
        $result = $lifecycle->disconnectSession($reseller, $customer, $request);

        return back()->with('status', $result['message']);
    }
}
