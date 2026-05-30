<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Billing\CustomerPrepayService;
use App\Support\PortalPaymentGateways;
use App\Support\PublicPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalBillController extends Controller
{
    public function index(Request $request, CustomerPrepayService $prepay): View
    {
        $customer = $request->user('customer');
        $customer->loadMissing('package');

        $invoiceQuery = Invoice::query()
            ->where('customer_id', $customer->id);

        $invoices = (clone $invoiceQuery)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15);

        $dueInvoices = (clone $invoiceQuery)
            ->whereIn('status', ['open', 'partial', 'draft'])
            ->get();

        $totalDue = $dueInvoices->sum(fn (Invoice $inv) => max(0, round((float) $inv->total - (float) $inv->amount_paid, 2)));

        $paymentMethods = PortalPaymentGateways::methodsForCustomerPortal();
        $gateways = PublicPaymentMethod::legacyFlags($paymentMethods);

        return view('portal.bills.index', [
            'invoices' => $invoices,
            'totalDue' => $totalDue,
            'unpaidInvoices' => $dueInvoices->count(),
            'nextDueDate' => $dueInvoices->filter(fn (Invoice $inv) => $inv->due_date !== null)->sortBy('due_date')->first()?->due_date,
            'gatewayCount' => count($paymentMethods),
            'gateways' => $gateways,
            'paymentMethods' => $paymentMethods,
            'bkashEnabled' => $gateways['bkash'],
            'sslcommerzEnabled' => $gateways['sslcommerz'],
            'nagadEnabled' => $gateways['nagad'],
            'rocketEnabled' => $gateways['rocket'],
            'piprapayEnabled' => $gateways['piprapay'],
            'prepayEnabled' => $prepay->isEnabled(),
            'prepayQuote' => $prepay->isEnabled() ? $prepay->quote($customer, 1) : null,
            'prepayMaxMonths' => $prepay->maxMonths(),
            'prepayQuickMonths' => $prepay->quickMonthOptions(),
        ]);
    }
}
