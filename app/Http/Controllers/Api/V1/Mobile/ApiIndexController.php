<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiIndexController extends Controller
{
    public function show(): JsonResponse
    {
        return response()->json([
            'name' => 'ISP Platform Mobile API',
            'version' => 'v1',
            'status' => 'ok',
            'message' => 'Use specific endpoints below. Open /api/v1/mobile/config for app settings.',
            'architecture' => url('/docs/MOBILE_ARCHITECTURE.md'),
            'docs' => url('/docs/API_V1.md'),
            'auth_modes' => [
                'staff' => 'sanctum bearer token',
                'customer' => 'sanctum bearer token',
                'reseller' => 'sanctum bearer token',
            ],
            'endpoints' => [
                'config' => url('/api/v1/mobile/config'),
                'login' => url('/api/v1/mobile/login'),
                'staff_refresh' => url('/api/v1/auth/refresh'),
                'customer_refresh' => url('/api/v1/customer/auth/refresh'),
                'staff_dashboard' => url('/api/v1/staff/dashboard'),
                'customer_dashboard' => url('/api/v1/customer/dashboard'),
                'reseller_dashboard' => url('/api/v1/reseller/dashboard'),
            ],
            'apps' => ['customer', 'collector', 'technician', 'noc', 'admin'],
        ]);
    }
}
