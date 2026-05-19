<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\Payments\PublicPaymentOrchestrator;
use App\Support\PaymentGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PortalInvoicePaymentController extends Controller
{
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer !== null && (int) $invoice->customer_id === (int) $customer->getAuthIdentifier(), 404);

        $validated = $request->validate([
            'gateway' => ['required', 'string', 'in:'.implode(',', [
                PaymentGateway::BKASH,
                PaymentGateway::SSLCOMMERZ,
                PaymentGateway::NAGAD,
                PaymentGateway::ROCKET,
                PaymentGateway::PIPRAPAY,
            ])],
        ]);

        $balance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        if ($balance <= 0 || in_array($invoice->status, ['void', 'cancelled', 'paid'], true)) {
            return redirect()
                ->route('portal.invoices.show', $invoice)
                ->with('danger', 'This invoice cannot be paid.');
        }

        return app(PublicPaymentOrchestrator::class)->startInvoicePayment(
            $invoice,
            $balance,
            $validated['gateway'],
            'portal',
        );
    }
}
