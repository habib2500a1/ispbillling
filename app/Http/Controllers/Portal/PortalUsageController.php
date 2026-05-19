<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CustomerBandwidthService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PortalUsageController extends Controller
{
    public function __construct(
        private readonly CustomerBandwidthService $bandwidth,
    ) {}

    public function index(): View
    {
        $customer = auth('customer')->user();
        $stats = $this->bandwidth->liveStats($customer);

        return view('portal.usage', [
            'customer' => $customer,
            'stats' => $stats,
        ]);
    }

    public function live(): JsonResponse
    {
        $customer = auth('customer')->user();

        return response()->json($this->bandwidth->liveStats($customer));
    }
}
