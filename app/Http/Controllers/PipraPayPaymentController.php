<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PendingGatewayPayment;
use App\Services\Payments\PipraPayCheckoutService;
use App\Services\Payments\PipraPayCheckoutStore;
use App\Services\Payments\PublicCheckoutSession;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PipraPayPaymentController extends Controller
{
    public function success(Request $request): RedirectResponse
    {
        $ppId = $this->resolvePpIdFromRequest($request);
        if ($ppId === null) {
            $orderId = $request->query('order_id');
            if (is_string($orderId) && $orderId !== '') {
                $ppId = $this->resolvePpIdFromOrderId($orderId);
            }
        }

        if ($ppId === null) {
            return redirect()->route('bill-payment.index')->with('danger', 'PipraPay payment reference missing.');
        }

        return $this->completeVerifiedPayment($ppId, $request->query(), 'piprapay_success');
    }

    public function cancel(Request $request): RedirectResponse
    {
        $orderId = $request->query('order_id');
        $pending = is_string($orderId) && $orderId !== '' ? PublicCheckoutSession::get($orderId) : null;
        if (is_string($orderId) && $orderId !== '') {
            PublicCheckoutSession::forget($orderId);
        }

        if ($pending !== null) {
            return $this->fail($pending, 'PipraPay payment was cancelled.');
        }

        return redirect()->route('bill-payment.index')->with('danger', 'PipraPay payment was cancelled.');
    }

    public function webhook(Request $request): JsonResponse
    {
        if (! PipraPayCheckoutService::isEnabled()) {
            return response()->json(['status' => false, 'message' => 'Disabled'], 503);
        }

        $service = PipraPayCheckoutService::fromConfig();
        $apiKey = $request->header('MHS-PIPRAPAY-API-KEY')
            ?? $request->header('mh-piprapay-api-key');
        $handled = $service->handleWebhook($apiKey);

        if (! ($handled['status'] ?? false)) {
            return response()->json(['status' => false, 'message' => 'Unauthorized'], 401);
        }

        $payload = $handled['data'];
        $ppId = $this->resolvePpIdFromPayload($payload);
        if ($ppId === null) {
            return response()->json(['status' => false, 'message' => 'Missing pp_id'], 422);
        }

        try {
            $this->completeVerifiedPayment($ppId, $payload, 'piprapay_webhook', jsonResponse: true);
        } catch (\Throwable $e) {
            Log::warning('piprapay.webhook_failed', ['pp_id' => $ppId, 'error' => $e->getMessage()]);

            return response()->json(['status' => false, 'message' => $e->getMessage()], 422);
        }

        return response()->json(['status' => true]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function completeVerifiedPayment(
        string $ppId,
        array $context,
        string $source,
        bool $jsonResponse = false,
    ): RedirectResponse|JsonResponse {
        $existing = Payment::query()
            ->where('gateway', PaymentGateway::PIPRAPAY)
            ->where(function ($q) use ($ppId): void {
                $q->where('gateway_transaction_id', $ppId)
                    ->orWhere('reference', $ppId)
                    ->orWhere('meta->piprapay_pp_id', $ppId);
            })
            ->where('status', 'completed')
            ->first();

        if ($existing !== null) {
            $orderId = PipraPayCheckoutService::orderIdForPpId($ppId);
            if ($orderId !== null) {
                PublicCheckoutSession::forget($orderId);
            }

            if ($jsonResponse) {
                return response()->json(['status' => true, 'message' => 'Already recorded']);
            }

            $pending = $existing->meta['return_to'] ?? 'bill_payment';
            $invoice = $existing->invoice_id ? Invoice::query()->find($existing->invoice_id) : null;

            return $this->successRedirect(
                ['return_to' => $pending, 'payment_type' => $existing->payment_type ?? PaymentType::PAYMENT],
                $invoice,
                'Payment was already recorded.',
                $existing,
            );
        }

        $service = PipraPayCheckoutService::fromConfig();

        try {
            $verified = $service->verifyPayment($ppId);
        } catch (\Throwable $e) {
            Log::warning('piprapay.verify_failed', ['pp_id' => $ppId, 'error' => $e->getMessage()]);

            if ($jsonResponse) {
                return response()->json(['status' => false, 'message' => 'Verification failed'], 422);
            }

            return redirect()->route('bill-payment.index')->with('danger', 'Could not verify PipraPay payment.');
        }

        if (! $service->isPaymentSuccessful($verified)) {
            if ($service->isPaymentPending($verified)) {
                $message = 'Payment is awaiting approval on PipraPay. Your balance will update automatically once approved (usually within a few minutes).';

                if ($jsonResponse) {
                    return response()->json(['status' => true, 'message' => $message, 'pending' => true]);
                }

                return redirect()->route('bill-payment.index')->with('status', $message);
            }

            if ($jsonResponse) {
                return response()->json(['status' => false, 'message' => 'Payment not successful'], 422);
            }

            return redirect()->route('bill-payment.index')->with('danger', 'PipraPay payment was not successful.');
        }

        $orderId = $service->orderIdFromVerify($verified, $ppId)
            ?? (is_string($context['order_id'] ?? null) ? $context['order_id'] : null)
            ?? PipraPayCheckoutService::orderIdForPpId($ppId);

        $pending = PipraPayCheckoutStore::resolve($orderId, $ppId, $verified);
        if ($pending === null) {
            Log::warning('piprapay.checkout_session_missing', [
                'pp_id' => $ppId,
                'order_id' => $orderId,
                'source' => $source,
            ]);

            if ($jsonResponse) {
                return response()->json(['status' => false, 'message' => 'Checkout session expired'], 422);
            }

            return redirect()->route('bill-payment.index')->with('danger', 'Payment session expired. If money was deducted, contact support with your PipraPay reference.');
        }

        $orderId ??= PipraPayCheckoutService::orderIdForPpId($ppId);
        if ($orderId === null) {
            $decoded = $verified['metadata'] ?? ($verified['data']['metadata'] ?? null);
            if (is_string($decoded)) {
                try {
                    $meta = json_decode($decoded, true, 512, JSON_THROW_ON_ERROR);
                    $orderId = is_array($meta) ? ($meta['order_id'] ?? null) : null;
                } catch (\JsonException) {
                    $orderId = null;
                }
            } elseif (is_array($decoded)) {
                $orderId = $decoded['order_id'] ?? null;
            }
        }

        $invoice = isset($pending['invoice_id']) ? Invoice::query()->find($pending['invoice_id']) : null;
        $paidAmount = $service->verifiedAmount($verified, (string) $pending['amount']);

        if (abs((float) $paidAmount - (float) $pending['amount']) > 0.05) {
            if ($jsonResponse) {
                return response()->json(['status' => false, 'message' => 'Amount mismatch'], 422);
            }

            return $this->fail($pending, 'Paid amount mismatch. Contact support.');
        }

        $paymentType = (string) ($pending['payment_type'] ?? PaymentType::PAYMENT);

        $payment = DB::transaction(function () use ($pending, $invoice, $ppId, $paidAmount, $verified, $paymentType, $orderId, $context, $source): Payment {
            return Payment::createTrusted([
                'customer_id' => (int) $pending['customer_id'],
                'invoice_id' => $invoice?->id,
                'amount' => $paidAmount,
                'method' => PaymentGateway::PIPRAPAY,
                'gateway' => PaymentGateway::PIPRAPAY,
                'gateway_transaction_id' => $ppId,
                'reference' => $ppId,
                'status' => 'completed',
                'paid_at' => now(),
                'payment_type' => $paymentType,
                'meta' => [
                    'piprapay_pp_id' => $ppId,
                    'piprapay_order_id' => $orderId,
                    'piprapay_verify' => $verified,
                    'piprapay_context' => $context,
                    'prepay_months' => $pending['prepay_months'] ?? null,
                    'source' => $source,
                    'return_to' => $pending['return_to'] ?? null,
                ],
            ]);
        });

        if (is_string($orderId) && $orderId !== '') {
            PublicCheckoutSession::forget($orderId);
            PipraPayCheckoutStore::markCompleted($orderId, $payment->id);
        }
        Cache::forget(PipraPayCheckoutService::ppCacheKey($ppId));

        $message = $paymentType === PaymentType::WALLET_DEPOSIT
            ? 'Wallet top-up recorded successfully.'
            : 'PipraPay payment recorded successfully.';

        if ($jsonResponse) {
            return response()->json(['status' => true, 'payment_id' => $payment->id]);
        }

        return $this->successRedirect($pending, $invoice, $message, $payment);
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
    private function fail(array $pending, string $message): RedirectResponse
    {
        $returnTo = $pending['return_to'] ?? 'bill_payment';
        $invoice = isset($pending['invoice_id']) ? Invoice::query()->find($pending['invoice_id']) : null;

        if ($returnTo === 'portal' && $invoice) {
            return redirect()->route('portal.invoices.show', $invoice)->with('danger', $message);
        }

        return redirect()->route('bill-payment.invoice')->with('danger', $message);
    }

    private function resolvePpIdFromOrderId(string $orderId): ?string
    {
        $row = PendingGatewayPayment::query()
            ->withoutGlobalScopes()
            ->where('gateway', PaymentGateway::PIPRAPAY)
            ->where('checkout_order_id', $orderId)
            ->where('status', PendingGatewayPayment::STATUS_PENDING)
            ->first();

        if ($row === null) {
            return null;
        }

        $fromMeta = $row->meta['pp_id'] ?? null;

        return is_string($fromMeta) && $fromMeta !== ''
            ? $fromMeta
            : ((string) $row->transaction_id !== '' ? (string) $row->transaction_id : null);
    }

    private function resolvePpIdFromRequest(Request $request): ?string
    {
        foreach (['pp_id', 'ppId', 'bp_id', 'payment_id', 'pp_tran_id', 'transaction_ref', 'gateway_trx', 'trx_id', 'transaction_id'] as $key) {
            $value = $request->input($key);
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolvePpIdFromPayload(array $payload): ?string
    {
        foreach (['pp_id', 'ppId', 'bp_id', 'payment_id', 'pp_tran_id', 'transaction_ref', 'gateway_trx', 'trx_id', 'transaction_id'] as $key) {
            $value = $payload[$key] ?? null;
            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        if (isset($payload['data']) && is_array($payload['data'])) {
            return $this->resolvePpIdFromPayload($payload['data']);
        }

        return null;
    }
}
