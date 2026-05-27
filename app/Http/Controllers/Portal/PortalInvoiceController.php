<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Support\PortalPaymentGateways;
use App\Support\PublicPaymentMethod;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $customer = $request->user('customer');

        $invoiceQuery = Invoice::query()
            ->where('customer_id', $customer->id);

        $invoices = (clone $invoiceQuery)
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->paginate(15);

        $allInvoices = (clone $invoiceQuery)->get();
        $openInvoices = $allInvoices->filter(fn (Invoice $invoice) => in_array($invoice->status, ['open', 'partial', 'draft'], true));

        return view('portal.invoices.index', [
            'invoices' => $invoices,
            'invoiceCount' => $allInvoices->count(),
            'openInvoiceCount' => $openInvoices->count(),
            'paidInvoiceCount' => $allInvoices->where('status', 'paid')->count(),
            'outstandingTotal' => round((float) $openInvoices->sum(fn (Invoice $invoice) => max(0, (float) $invoice->total - (float) $invoice->amount_paid)), 2),
        ]);
    }

    public function show(Request $request, Invoice $invoice): View
    {
        $customer = $request->user('customer');
        abort_unless((int) $invoice->customer_id === (int) $customer->id, 404);

        $invoice->load(['items']);

        $due = round((float) $invoice->total - (float) $invoice->amount_paid, 2);

        $paymentMethods = PortalPaymentGateways::methodsForCustomerPortal();
        $gateways = PublicPaymentMethod::legacyFlags($paymentMethods);

        return view('portal.invoices.show', [
            'invoice' => $invoice,
            'balanceDue' => $due,
            'gateways' => $gateways,
            'paymentMethods' => $paymentMethods,
            'bkashEnabled' => $gateways['bkash'],
            'sslcommerzEnabled' => $gateways['sslcommerz'],
            'nagadEnabled' => $gateways['nagad'],
            'rocketEnabled' => $gateways['rocket'],
            'piprapayEnabled' => $gateways['piprapay'],
            'canPay' => $due > 0 && ! in_array($invoice->status, ['void', 'cancelled', 'paid'], true) && $gateways['any'],
        ]);
    }
}
