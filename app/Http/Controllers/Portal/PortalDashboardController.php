<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Outage;
use App\Services\Billing\CustomerPrepayService;
use App\Services\Portal\CustomerPortalDashboardService;
use App\Support\PortalPaymentGateways;
use App\Support\PublicPaymentMethod;
use App\Services\Portal\CustomerPortalNotificationService;
use App\Services\Portal\PortalContentCatalog;
use App\Services\Portal\PortalMovieServerCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class PortalDashboardController extends Controller
{
    public function index(
        CustomerPortalDashboardService $dashboard,
        CustomerPortalNotificationService $notifications,
        CustomerPrepayService $prepay,
    ): View
    {
        /** @var Customer $customer */
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

        $paymentMethods = PortalPaymentGateways::methodsForCustomerPortal($customer);

        return view('portal.dashboard', [
            'customer' => $customer,
            'recentInvoices' => $recentInvoices,
            'outages' => $outages,
            'dash' => $dashboard->payload($customer),
            'prepayEnabled' => $prepay->isEnabled(),
            'prepayQuote' => $prepay->isEnabled() ? $prepay->quote($customer, 1) : null,
            'prepayMaxMonths' => $prepay->maxMonths(),
            'prepayQuickMonths' => $prepay->quickMonthOptions(),
            'paymentMethods' => $paymentMethods,
            'gateways' => PublicPaymentMethod::legacyFlags($paymentMethods),
            'notificationFeed' => $notifications->feed($customer, 4),
            'notificationSummary' => $notifications->summary($customer),
            'movieServers' => PortalMovieServerCatalog::forPortal((int) $customer->tenant_id),
            'portalNotices' => PortalContentCatalog::noticesForPortal((int) $customer->tenant_id),
            'portalMarquee' => PortalContentCatalog::marqueeForPortal((int) $customer->tenant_id),
        ]);
    }

    public function live(CustomerPortalDashboardService $dashboard): JsonResponse
    {
        /** @var Customer $customer */
        $customer = auth('customer')->user();

        return response()->json($dashboard->payload($customer));
    }
}
