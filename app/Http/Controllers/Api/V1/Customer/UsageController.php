<?php

namespace App\Http\Controllers\Api\V1\Customer;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\Mobile\CustomerMobileService;
use App\Services\Portal\CustomerBandwidthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    public function live(Request $request, CustomerBandwidthService $bandwidth, CustomerMobileService $mobile): JsonResponse
    {
        /** @var Customer $customer */
        $customer = $request->user();

        $stats = $bandwidth->liveStats($customer);
        $payload = $mobile->usagePayload($stats);
        $payload['chart'] = $stats['chart'] ?? $bandwidth->chartForCustomer($customer, 12);
        $payload['uptime'] = $stats['session_started'] ?? null;
        $payload['anomaly'] = $this->detectAnomaly($stats);

        return response()->json(['usage' => $payload]);
    }

    /**
     * @param  array<string, mixed>  $stats
     * @return array{flagged: bool, reason: string|null}
     */
    private function detectAnomaly(array $stats): array
    {
        $down = (int) ($stats['download_bps'] ?? 0);
        $up = (int) ($stats['upload_bps'] ?? 0);
        if (($stats['online'] ?? false) && $down > 500_000_000) {
            return ['flagged' => true, 'reason' => 'Unusually high download rate'];
        }
        if (($stats['online'] ?? false) && $up > 200_000_000) {
            return ['flagged' => true, 'reason' => 'Unusually high upload rate'];
        }

        return ['flagged' => false, 'reason' => null];
    }
}
