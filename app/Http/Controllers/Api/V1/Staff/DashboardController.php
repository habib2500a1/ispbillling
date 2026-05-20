<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Http\Controllers\Controller;
use App\Services\Mobile\StaffMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function show(Request $request, StaffMobileService $mobile): JsonResponse
    {
        $user = $request->user();

        return response()->json($mobile->dashboard($user));
    }
}
