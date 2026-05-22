<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\PortalPaymentGateways;
use App\Support\PublicPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalBillController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15);

        $totalDue = Invoice::query()
            ->where('customer_id', $customer->id)
            ->whereIn('status', ['open', 'partial', 'draft'])
            ->get()
            ->sum(fn (Invoice $inv) => max(0, round((float) $inv->total - (float) $inv->amount_paid, 2)));

        $paymentMethods = PortalPaymentGateways::methodsForCustomerPortal();
        $gateways = PublicPaymentMethod::legacyFlags($paymentMethods);

        return view('portal.bills.index', [
            'invoices' => $invoices,
            'totalDue' => $totalDue,
            'gateways' => $gateways,
            'paymentMethods' => $paymentMethods,
            'bkashEnabled' => $gateways['bkash'],
            'sslcommerzEnabled' => $gateways['sslcommerz'],
            'nagadEnabled' => $gateways['nagad'],
            'rocketEnabled' => $gateways['rocket'],
            'piprapayEnabled' => $gateways['piprapay'],
        ]);
    }
}
