<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Outage;
use App\Services\Portal\CustomerPortalDashboardService;
use App\Services\Portal\PortalContentCatalog;
use App\Services\Portal\PortalMovieServerCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PortalDashboardController extends Controller
{
    public function index(CustomerPortalDashboardService $dashboard): View
    {
        $customer = auth('customer')->user();
        $customer->loadMissing('package:id,name,download_mbps,upload_mbps,price_monthly');

        $recentInvoices = $customer->invoices()
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'invoice_number', 'total', 'amount_paid', 'status', 'issue_date']);

        $outages = Outage::query()
            ->currentlyActive()
            ->forCustomerArea($customer->area_id)
            ->where('tenant_id', $customer->tenant_id)
            ->orderByDesc('started_at')
            ->limit(5)
            ->get(['id', 'title', 'description', 'started_at']);

        return view('portal.dashboard', [
            'customer' => $customer,
            'recentInvoices' => $recentInvoices,
            'outages' => $outages,
            'dash' => $dashboard->payload($customer),
            'movieServers' => PortalMovieServerCatalog::forPortal((int) $customer->tenant_id),
            'portalNotices' => PortalContentCatalog::noticesForPortal((int) $customer->tenant_id),
            'portalMarquee' => PortalContentCatalog::marqueeForPortal((int) $customer->tenant_id),
        ]);
    }

    public function live(CustomerPortalDashboardService $dashboard): JsonResponse
    {
        $customer = auth('customer')->user();

        return response()->json($dashboard->payload($customer));
    }
}
