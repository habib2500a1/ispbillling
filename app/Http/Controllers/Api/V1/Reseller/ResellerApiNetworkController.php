<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Resellers\ResellerCustomerLifecycleService;
use App\Services\Resellers\ResellerCustomerService;
use App\Services\Resellers\ResellerNetworkSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiNetworkController extends Controller
{
    public function index(Request $request, ResellerNetworkSessionService $sessions): JsonResponse
    {
        $reseller = $request->user();
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

        $clients = $query->orderBy('name')->paginate(min(50, (int) $request->query('per_page', 25)));
        $sessionMap = $sessions->summarizeMany($clients->getCollection(), refreshLiveForOnline: false);

        $clients->through(function (Customer $client) use ($sessionMap): array {
            return array_merge(
                $client->only(['id', 'customer_code', 'name', 'status', 'is_ppp_online']),
                [
                    'package' => $client->package?->name,
                    'session' => $sessionMap[$client->id] ?? [],
                ],
            );
        });

        return response()->json([
            'online_count' => $reseller->customers()->where('is_ppp_online', true)->count(),
            'offline_count' => $reseller->customers()->where('is_ppp_online', false)->where('status', 'active')->count(),
            'clients' => $clients,
        ]);
    }

    public function session(
        Customer $customer,
        Request $request,
        ResellerCustomerService $customers,
        ResellerNetworkSessionService $sessions,
    ): JsonResponse {
        $customers->assertOwned($request->user(), $customer);

        return response()->json($sessions->liveDetail($customer));
    }

    public function disconnect(Request $request, Customer $customer, ResellerCustomerLifecycleService $lifecycle): JsonResponse
    {
        $result = $lifecycle->disconnectSession($request->user(), $customer, $request);

        return response()->json($result);
    }
}
