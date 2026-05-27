<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\NagadCheckoutService;
use App\Services\Payments\PublicCheckoutSession;
use App\Support\CheckoutPaymentMeta;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NagadPaymentController extends Controller
{
    public function callback(Request $request): RedirectResponse
    {
        $orderId = $request->query('order_id');
        $paymentRefId = $request->query('payment_ref_id');
        $status = $request->query('status');

        if (! is_string($orderId) || $orderId === '') {
            return redirect()->route('bill-payment.index')->with('danger', 'Nagad callback missing order ID.');
        }

        $pending = PublicCheckoutSession::get($orderId);
        if ($pending === null) {
            return redirect()->route('bill-payment.index')->with('danger', 'Payment session expired.');
        }

        $invoice = isset($pending['invoice_id']) ? Invoice::query()->find($pending['invoice_id']) : null;

        if ($status !== 'Success') {
            PublicCheckoutSession::forget($orderId);

            return $this->fail($pending, 'Nagad payment was not successful.');
        }

        if (! is_string($paymentRefId) || $paymentRefId === '') {
            return $this->fail($pending, 'Nagad payment reference missing.');
        }

        $existing = Payment::query()
            ->where('gateway', PaymentGateway::NAGAD)
            ->where('meta->nagad_payment_ref_id', $paymentRefId)
            ->where('status', 'completed')
            ->first();

        if ($existing) {
            PublicCheckoutSession::forget($orderId);

            return $this->successRedirect($pending, $invoice, 'Payment was already recorded.', $existing);
        }

        try {
            $verified = NagadCheckoutService::fromConfig()->verifyPayment($paymentRefId);
        } catch (\Throwable $e) {
            Log::warning('nagad.verify_failed', ['order_id' => $orderId, 'error' => $e->getMessage()]);

            return $this->fail($pending, 'Could not verify Nagad payment.');
        }

        if (($verified['status'] ?? '') !== 'Success') {
            return $this->fail($pending, 'Nagad verification failed.');
        }

        $paidAmount = number_format((float) ($verified['amount'] ?? $pending['amount']), 2, '.', '');
        if (abs((float) $paidAmount - (float) $pending['amount']) > 0.05) {
            return $this->fail($pending, 'Paid amount mismatch. Contact support.');
        }

        $paymentType = (string) ($pending['payment_type'] ?? PaymentType::PAYMENT);

        $payment = DB::transaction(function () use ($pending, $invoice, $paymentRefId, $paidAmount, $verified, $paymentType, $orderId, $request): Payment {
            return Payment::createTrusted([
                'customer_id' => (int) $pending['customer_id'],
                'invoice_id' => $invoice?->id,
                'amount' => $paidAmount,
                'method' => PaymentGateway::NAGAD,
                'gateway' => PaymentGateway::NAGAD,
                'gateway_transaction_id' => $paymentRefId,
                'reference' => $paymentRefId,
                'status' => 'completed',
                'paid_at' => now(),
                'payment_type' => $paymentType,
                'meta' => CheckoutPaymentMeta::fromSession($pending, [
                    'nagad_payment_ref_id' => $paymentRefId,
                    'nagad_order_id' => $orderId,
                    'nagad_verify' => $verified,
                    'nagad_callback' => $request->query(),
                    'source' => 'nagad_callback',
                ]),
            ]);
        });

        PublicCheckoutSession::forget($orderId);

        $message = match ($paymentType) {
            PaymentType::WALLET_DEPOSIT => 'Wallet top-up recorded successfully.',
            PaymentType::PREPAY => 'Advance payment recorded successfully.',
            default => 'Nagad payment recorded successfully.',
        };

        return $this->successRedirect($pending, $invoice, $message, $payment);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function successRedirect(array $pending, ?Invoice $invoice, string $message, Payment $payment): RedirectResponse
    {
        $returnTo = $pending['return_to'] ?? 'bill_payment';

        if ($returnTo === 'portal') {
            if ($invoice) {
                return redirect()->route('portal.invoices.show', $invoice)->with('status', $message);
            }

            return redirect()->route('portal.bills.index')->with('status', $message);
        }

        if ($returnTo === 'bill_payment') {
            return redirect()->route('bill-payment.receipt', $payment)->with('status', $message);
        }

        if ($invoice) {
            return redirect()->route('filament.admin.resources.invoices.edit', ['record' => $invoice])
                ->with('success', $message);
        }

        return redirect()->route('bill-payment.index')->with('status', $message);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function fail(array $pending, string $message): RedirectResponse
    {
        $returnTo = $pending['return_to'] ?? 'bill_payment';
        $invoice = isset($pending['invoice_id']) ? Invoice::query()->find($pending['invoice_id']) : null;

        if ($returnTo === 'portal' && $invoice) {
            return redirect()->route('portal.invoices.show', $invoice)->with('danger', $message);
        }

        return redirect()->route('bill-payment.invoice')->with('danger', $message);
    }
}
