<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\PublicCheckoutSession;
use App\Services\Payments\SslCommerzCheckoutService;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SslCommerzPaymentController extends Controller
{
    public function success(Request $request): RedirectResponse
    {
        $valId = $request->query('val_id');
        $tranId = $request->query('tran_id');

        if (! is_string($tranId) || $tranId === '') {
            return redirect()->route('bill-payment.index')->with('danger', 'SSLCommerz callback missing transaction ID.');
        }

        $pending = PublicCheckoutSession::get($tranId);
        if ($pending === null) {
            return redirect()->route('bill-payment.index')->with('danger', 'Payment session expired. Please try again.');
        }

        if (! is_string($valId) || $valId === '') {
            return $this->checkoutFailure($pending, 'SSLCommerz validation ID missing.');
        }

        $invoice = isset($pending['invoice_id']) ? Invoice::query()->find($pending['invoice_id']) : null;

        $existing = Payment::query()
            ->where('gateway', PaymentGateway::SSLCOMMERZ)
            ->where('meta->sslcommerz_tran_id', $tranId)
            ->where('status', 'completed')
            ->first();

        if ($existing) {
            PublicCheckoutSession::forget($tranId);

            return $this->successRedirect($pending, $invoice, 'Payment was already recorded.', $existing);
        }

        try {
            $validated = SslCommerzCheckoutService::fromConfig()->validatePayment($valId);
        } catch (\Throwable $e) {
            Log::warning('sslcommerz.validate_failed', ['tran_id' => $tranId, 'error' => $e->getMessage()]);

            return $this->checkoutFailure($pending, $e->getMessage());
        }

        if (strtoupper($validated['status']) !== 'VALID' && strtoupper($validated['status']) !== 'VALIDATED') {
            return $this->checkoutFailure($pending, 'SSLCommerz payment was not validated.');
        }

        if (($validated['tran_id'] ?? $tranId) !== $tranId) {
            return $this->checkoutFailure($pending, 'Transaction ID mismatch.');
        }

        $paidAmount = (string) ($validated['amount'] ?? $pending['amount']);
        if (abs((float) $paidAmount - (float) $pending['amount']) > 0.05) {
            return $this->checkoutFailure($pending, 'Paid amount mismatch. Contact support.');
        }

        $paymentType = (string) ($pending['payment_type'] ?? PaymentType::PAYMENT);
        $trxId = is_string($request->query('bank_tran_id')) ? $request->query('bank_tran_id') : $valId;

        $payment = DB::transaction(function () use ($pending, $invoice, $tranId, $paidAmount, $trxId, $validated, $paymentType, $valId): Payment {
            return Payment::createTrusted([
                'customer_id' => (int) $pending['customer_id'],
                'invoice_id' => $invoice?->id,
                'amount' => $paidAmount,
                'method' => PaymentGateway::SSLCOMMERZ,
                'gateway' => PaymentGateway::SSLCOMMERZ,
                'gateway_transaction_id' => $trxId,
                'reference' => $trxId,
                'status' => 'completed',
                'paid_at' => now(),
                'payment_type' => $paymentType,
                'meta' => [
                    'sslcommerz_tran_id' => $tranId,
                    'sslcommerz_val_id' => $valId,
                    'sslcommerz_validate' => $validated['raw'],
                    'source' => 'sslcommerz_callback',
                    'return_to' => $pending['return_to'] ?? null,
                ],
            ]);
        });

        PublicCheckoutSession::forget($tranId);

        $message = $paymentType === PaymentType::WALLET_DEPOSIT
            ? 'Wallet top-up recorded successfully.'
            : 'Payment recorded successfully.';

        return $this->successRedirect($pending, $invoice, $message, $payment);
    }

    public function fail(Request $request): RedirectResponse
    {
        $tranId = $request->query('tran_id');
        if (is_string($tranId) && $tranId !== '') {
            PublicCheckoutSession::forget($tranId);
        }

        return redirect()->route('bill-payment.invoice')->with('danger', 'SSLCommerz payment failed.');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $tranId = $request->query('tran_id');
        if (is_string($tranId) && $tranId !== '') {
            PublicCheckoutSession::forget($tranId);
        }

        return redirect()->route('bill-payment.invoice')->with('danger', 'SSLCommerz payment was cancelled.');
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function successRedirect(array $pending, ?Invoice $invoice, string $message, Payment $payment): RedirectResponse
    {
        $returnTo = $pending['return_to'] ?? 'bill_payment';

        if ($returnTo === 'portal' && $invoice) {
            return redirect()->route('portal.invoices.show', $invoice)->with('status', $message);
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
    private function checkoutFailure(array $pending, string $message): RedirectResponse
    {
        $returnTo = $pending['return_to'] ?? 'bill_payment';
        $invoice = isset($pending['invoice_id']) ? Invoice::query()->find($pending['invoice_id']) : null;

        if ($returnTo === 'portal' && $invoice) {
            return redirect()->route('portal.invoices.show', $invoice)->with('danger', $message);
        }

        return redirect()->route('bill-payment.invoice')->with('danger', $message);
    }
}
