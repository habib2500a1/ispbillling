<?php

namespace App\Http\Controllers\Api\V1\Reseller;

use App\Http\Controllers\Controller;
use App\Services\Resellers\ResellerEnterpriseReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResellerApiEnterpriseReportController extends Controller
{
    public function show(Request $request, ResellerEnterpriseReportService $reports): JsonResponse
    {
        $reseller = $request->user();

        return response()->json([
            'revenue' => $reports->revenueReport($reseller),
            'growth' => $reports->customerGrowthReport($reseller),
            'package_sales' => $reports->packageSalesReport($reseller),
            'profit_loss' => $reports->profitLossReport($reseller),
        ]);
    }
}
