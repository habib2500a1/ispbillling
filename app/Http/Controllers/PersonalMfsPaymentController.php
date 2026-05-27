<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Payments\GatewayPaymentVerificationService;
use App\Services\Payments\PublicCheckoutSession;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Support\PersonalMfsGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PersonalMfsPaymentController extends Controller
{
    public function checkout(Request $request, string $gateway): View|RedirectResponse
    {
        $gateway = strtolower($gateway);
        if (! PersonalMfsGateway::isPersonalEnabled($gateway)) {
            return redirect()->route('bill-payment.index')->with('danger', 'This payment method is not available.');
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

        return view('payments.personal-mfs', [
            'gateway' => $gateway,
            'gatewayLabel' => PaymentGateway::label($gateway),
            'orderId' => $orderId,
            'amount' => (float) ($session['amount'] ?? 0),
            'merchantNumber' => PersonalMfsGateway::merchantNumber($gateway),
            'merchantName' => PersonalMfsGateway::merchantName($gateway),
            'instructions' => config("{$gateway}.instructions") ?? config('rocket.instructions'),
            'customer' => $customer,
            'invoice' => $invoice,
            'returnTo' => (string) ($session['return_to'] ?? 'bill_payment'),
        ]);
    }

    public function confirm(Request $request, string $gateway): RedirectResponse
    {
        $gateway = strtolower($gateway);
        if (! PersonalMfsGateway::isPersonalEnabled($gateway)) {
            return redirect()->route('bill-payment.index')->with('danger', 'This payment method is not available.');
        }

        $minLen = max(4, (int) config("mfs_personal.gateways.{$gateway}.trx_min_length", 8));

        $validated = $request->validate([
            'order' => ['required', 'string', 'max:64'],
            'transaction_id' => ['required', 'string', "min:{$minLen}", 'max:64'],
        ]);

        $orderId = $validated['order'];
        $session = PublicCheckoutSession::get($orderId);
        if ($session === null) {
            return redirect()->route('bill-payment.index')->with('danger', 'Payment session expired.');
        }

        try {
            $result = app(GatewayPaymentVerificationService::class)->submitPersonalConfirmation(
                $gateway,
                $orderId,
                $validated['transaction_id'],
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

        if ($result['status'] === 'pending') {
            $notice = (string) ($result['customer_notice'] ?? $result['message']);

            return redirect()
                ->route('mfs.personal.checkout', ['gateway' => $gateway, 'order' => $orderId])
                ->with('mfs_pending', true)
                ->with('status', $notice)
                ->withInput();
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

        if ($paymentType === PaymentType::PREPAY) {
            return redirect()->route('bill-payment.invoice', ['tab' => 'prepay'])->with('status', $flash);
        }

        return redirect()->route('bill-payment.invoice')->with('status', $flash);
    }
}
