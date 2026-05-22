<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\StaffMonitoringService;
use App\Support\StaffTenantScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** @deprecated Use StaffMonitoringController — kept for backward compatibility */
class StaffOnlineController extends Controller
{
    public function index(Request $request, StaffMonitoringService $monitoring): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json($monitoring->onlineClients(StaffTenantScope::tenantIdFor($user)));
    }
}
