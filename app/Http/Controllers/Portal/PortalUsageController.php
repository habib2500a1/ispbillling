<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\Portal\CustomerBandwidthService;
use App\Support\CompanyBranding;
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
            'pollSeconds' => max(1, (int) config('portal.poll_seconds', 1)),
            'companyName' => CompanyBranding::name(),
            'speedtest' => [
                'ping_url' => (string) config('portal.speed_test.external.ping_url'),
                'download_url' => (string) config('portal.speed_test.external.download_url'),
                'upload_url' => (string) config('portal.speed_test.external.upload_url'),
            ],
        ]);
    }

    public function live(): JsonResponse
    {
        $customer = auth('customer')->user();

        return response()->json($this->bandwidth->liveStats($customer));
    }
}
