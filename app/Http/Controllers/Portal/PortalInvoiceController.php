<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\PortalPaymentGateways;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        $invoices = Invoice::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15);

        return view('portal.invoices.index', [
            'invoices' => $invoices,
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $customer = $request->user('customer');
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        $invoice->load(['items']);

        $due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);

        $gateways = PortalPaymentGateways::forCustomerPortal();

        return view('portal.invoices.show', [
            'invoice' => $invoice,
            'balanceDue' => $due,
            'gateways' => $gateways,
            'bkashEnabled' => $gateways['bkash'],
            'sslcommerzEnabled' => $gateways['sslcommerz'],
            'nagadEnabled' => $gateways['nagad'],
            'rocketEnabled' => $gateways['rocket'],
            'piprapayEnabled' => $gateways['piprapay'],
            'canPay' => $due > 0 && ! in_array($invoice->status, ['void', 'cancelled', 'paid'], true) && $gateways['any'],
        ]);
    }
}
