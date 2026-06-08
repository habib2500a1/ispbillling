<?php

namespace App\Http\Controllers;

use App\Models\PlatformInvoice;
use App\Services\Payments\PipraPayCheckoutService;
use App\Services\Payments\PublicCheckoutSession;
use App\Services\Tenant\PlatformInvoicePaymentService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Support\PersonalMfsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PlatformInvoicePaymentController extends Controller
{
    public function show(string $token, PlatformInvoicePaymentService $payments): View|RedirectResponse
    {
        $invoice = $payments->findByToken($token);
        if ($invoice === null) {
            abort(404);
        }

        if ($invoice->isPaid()) {
            return view('payments.platform-invoice-paid', ['invoice' => $invoice]);
        }

        $invoice->markOverdueIfNeeded();

        return view('payments.platform-invoice-pay', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'piprapayEnabled' => PipraPayCheckoutService::isEnabled(),
            'bkashEnabled' => PersonalMfsGateway::isPersonalEnabled(PaymentGateway::BKASH),
            'nagadEnabled' => PersonalMfsGateway::isPersonalEnabled(PaymentGateway::NAGAD),
            'rocketEnabled' => PersonalMfsGateway::isPersonalEnabled(PaymentGateway::ROCKET),
        ]);
    }

    public function pipraPay(string $token, PlatformInvoicePaymentService $payments): RedirectResponse
    {
        $invoice = $payments->findByToken($token);
        abort_unless($invoice !== null, 404);

        $checkout = $payments->initiatePipraPay($invoice);

        return redirect()->away($checkout['redirect_url']);
    }

    public function personalMfs(Request $request, string $token, string $gateway, PlatformInvoicePaymentService $payments): View|RedirectResponse
    {
        $invoice = $payments->findByToken($token);
        abort_unless($invoice !== null, 404);

        $gateway = strtolower($gateway);
        $orderId = (string) $request->query('order', '');
        $session = PublicCheckoutSession::get($orderId);

        if ($session === null || (int) ($session['platform_invoice_id'] ?? 0) !== (int) $invoice->id) {
            return redirect()->route('platform-invoice.pay', ['token' => $token])
                ->with('danger', 'Payment session expired. Please try again.');
        }

        if (! PersonalMfsGateway::isPersonalEnabled($gateway)) {
            return redirect()->route('platform-invoice.pay', ['token' => $token])
                ->with('danger', 'This payment method is not available.');
        }

        return view('payments.platform-invoice-mfs', [
            'invoice' => $invoice,
            'tenant' => $invoice->tenant,
            'gateway' => $gateway,
            'gatewayLabel' => PaymentGateway::label($gateway),
            'orderId' => $orderId,
            'amount' => (float) ($session['amount'] ?? $invoice->amount),
            'merchantNumber' => PersonalMfsGateway::merchantNumber($gateway),
            'merchantName' => PersonalMfsGateway::merchantName($gateway),
            'token' => $token,
        ]);
    }

    public function startMfs(Request $request, string $token, PlatformInvoicePaymentService $payments): RedirectResponse
    {
        $invoice = $payments->findByToken($token);
        abort_unless($invoice !== null, 404);

        $gateway = strtolower((string) $request->input('gateway', ''));
        $session = $payments->initiatePersonalMfs($invoice, $gateway);

        return redirect()->to($session['redirect_url']);
    }

    public function confirmMfs(Request $request, string $token, PlatformInvoicePaymentService $payments): RedirectResponse
    {
        $invoice = $payments->findByToken($token);
        abort_unless($invoice !== null, 404);

        $validated = $request->validate([
            'order' => ['required', 'string', 'max:64'],
            'transaction_id' => ['required', 'string', 'min:4', 'max:64'],
            'gateway' => ['required', 'string', 'max:32'],
        ]);

        $session = PublicCheckoutSession::get($validated['order']);
        if ($session === null || (int) ($session['platform_invoice_id'] ?? 0) !== (int) $invoice->id) {
            return redirect()->route('platform-invoice.pay', ['token' => $token])
                ->with('danger', 'Payment session expired.');
        }

        if ((string) ($session['payment_type'] ?? '') !== PaymentType::PLATFORM_SUBSCRIPTION) {
            return redirect()->route('platform-invoice.pay', ['token' => $token])
                ->with('danger', 'Invalid payment session.');
        }

        $payments->completePayment(
            $invoice,
            strtolower($validated['gateway']),
            $validated['transaction_id'],
            $validated['order'],
        );

        PublicCheckoutSession::forget($validated['order']);

        return redirect()->route('platform-invoice.pay', ['token' => $token])
            ->with('status', 'Payment recorded successfully. Thank you!');
    }
}
