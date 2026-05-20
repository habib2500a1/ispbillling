<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Mobile\StaffMonitoringService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffMonitoringController extends Controller
{
    public function index(Request $request, StaffMonitoringService $monitoring): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return response()->json($monitoring->onlineClients((int) $user->tenant_id));
    }

    public function live(Request $request, StaffMonitoringService $monitoring): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return response()->json($monitoring->liveSnapshot((int) $user->tenant_id));
    }
}
