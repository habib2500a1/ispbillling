<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Services\Optical\OpticalAiRiskService;
use App\Services\Optical\OpticalNocDashboardService;
use App\Services\Optical\OpticalSignalHistoryService;
use App\Support\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffOpticalNocController extends Controller
{
    public function dashboard(OpticalNocDashboardService $noc): JsonResponse
    {
        return response()->json([
            'snapshot' => $noc->fullSnapshot(TenantResolver::requiredTenantId()),
        ]);
    }

    public function signalHistory(Request $request, Device $device, OpticalSignalHistoryService $history): JsonResponse
    {
        $this->assertOnu($device);

        $period = (string) $request->query('period', '24h');
        if (! isset(OpticalSignalHistoryService::PERIODS[$period])) {
            $period = '24h';
        }

        return response()->json([
            'device_id' => $device->id,
            'period' => $period,
            'series' => $history->series((int) $device->id, $period),
        ]);
    }

    public function predictions(OpticalAiRiskService $ai): JsonResponse
    {
        $items = $ai->refreshTenantPredictions(TenantResolver::requiredTenantId(), 50);

        return response()->json(['predictions' => $items]);
    }

    public function ponPorts(OpticalSignalHistoryService $history): JsonResponse
    {
        return response()->json([
            'ports' => $history->ponPortStats(TenantResolver::requiredTenantId(), 100),
        ]);
    }

    private function assertOnu(Device $device): void
    {
        if ($device->type !== 'onu') {
            abort(404, 'Device is not an ONU.');
        }
    }
}
