<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Payments\GatewayPaymentVerificationService;
use App\Services\Payments\PublicCheckoutSession;
use App\Services\Payments\RocketCheckoutService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RocketPaymentController extends Controller
{
    public function checkout(Request $request): View|RedirectResponse
    {
        if (! RocketCheckoutService::isEnabled()) {
            return redirect()->route('bill-payment.index')->with('danger', 'Rocket payment is not available.');
        }

        $orderId = (string) $request->query('order', '');
        $session = PublicCheckoutSession::get($orderId);
        if ($session === null) {
            return redirect()->route('bill-payment.index')->with('danger', 'Payment session expired. Please try again.');
        }

        $customer = Customer::query()->withoutGlobalScopes()->find((int) ($session['customer_id'] ?? 0));
        if ($customer === null) {
            return redirect()->route('bill-payment.index')->with('danger', 'Customer not found.');
        }

        $invoice = isset($session['invoice_id'])
            ? Invoice::query()->withoutGlobalScopes()->find((int) $session['invoice_id'])
            : null;

        return view('payments.rocket', [
            'orderId' => $orderId,
            'amount' => (float) ($session['amount'] ?? 0),
            'merchantNumber' => config('rocket.merchant_number'),
            'merchantName' => config('rocket.merchant_name'),
            'instructions' => config('rocket.instructions'),
            'customer' => $customer,
            'invoice' => $invoice,
            'returnTo' => (string) ($session['return_to'] ?? 'bill_payment'),
        ]);
    }

    public function confirm(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'order' => ['required', 'string', 'max:64'],
            'transaction_id' => ['required', 'string', 'min:'.max(4, (int) config('rocket.trx_id_min_length', 8)), 'max:64'],
        ]);

        $orderId = $validated['order'];
        $session = PublicCheckoutSession::get($orderId);
        if ($session === null) {
            return redirect()->route('bill-payment.index')->with('danger', 'Payment session expired.');
        }

        $customerId = (int) ($session['customer_id'] ?? 0);
        $amount = (float) ($session['amount'] ?? 0);
        $invoiceId = isset($session['invoice_id']) ? (int) $session['invoice_id'] : null;
        $trxId = strtoupper(trim($validated['transaction_id']));

        try {
            $result = app(GatewayPaymentVerificationService::class)->submitRocketConfirmation(
                $orderId,
                $trxId,
                $session,
            );
        } catch (\Throwable $e) {
            return back()->with('danger', $e->getMessage())->withInput();
        }

        if ($result['status'] === 'duplicate') {
            return back()->with('danger', $result['message'])->withInput();
        }

        if ($result['status'] === 'error') {
            return back()->with('danger', $result['message'])->withInput();
        }

        PublicCheckoutSession::forget($orderId);

        $returnTo = (string) ($session['return_to'] ?? 'bill_payment');
        $paymentType = (string) ($session['payment_type'] ?? PaymentType::PAYMENT);
        $flash = $result['message'];

        if ($returnTo === 'portal') {
            return redirect()->route('portal.payments.index')->with('status', $flash);
        }

        if ($paymentType === PaymentType::WALLET_DEPOSIT) {
            return redirect()->route('bill-payment.invoice', ['tab' => 'wallet'])->with('status', $flash);
        }

        return redirect()->route('bill-payment.invoice')->with('status', $flash);
    }
}
