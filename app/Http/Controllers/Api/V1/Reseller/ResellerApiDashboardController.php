<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Resellers\ResellerPortalDashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiDashboardController extends Controller
{
    public function show(Request $request, ResellerPortalDashboardService $dashboard): JsonResponse
    {
        return response()->json([
            'metrics' => $dashboard->metrics($request->user()),
        ]);
    }
}
