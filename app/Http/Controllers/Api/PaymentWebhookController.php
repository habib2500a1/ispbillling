<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Payments\PaymentProcessor;
use App\Support\PaymentGateway;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymentWebhookController extends Controller
{
    /**
     * Generic payment webhook: POST /api/webhooks/payments/{gateway}
     *
     * Expected JSON (all gateways):
     * - secret (or X-Webhook-Secret header)
     * - transaction_id (required)
     * - amount (required)
     * - customer_id OR customer_code OR phone (required)
     * - invoice_id OR invoice_number (optional)
     * - reference (optional)
     */
    public function store(Request $request, string $gateway): JsonResponse
    {
        $gateway = strtolower($gateway);
        if (! in_array($gateway, PaymentGateway::webhookGateways(), true)) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        if (! $this->verifySecret($request, $gateway)) {
            return response()->json(['message' => 'Invalid webhook secret.'], 401);
        }

        if (! (bool) config("payments.gateways.{$gateway}.enabled", false)
            && $gateway !== PaymentGateway::BKASH) {
            Log::info('payment.webhook.disabled', ['gateway' => $gateway]);

            return response()->json(['message' => 'Gateway disabled.', 'accepted' => false], 202);
        }

        $data = $request->validate([
            'transaction_id' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'customer_id' => ['nullable', 'integer'],
            'customer_code' => ['nullable', 'string', 'max:64'],
            'phone' => ['nullable', 'string', 'max:32'],
            'invoice_id' => ['nullable', 'integer'],
            'invoice_number' => ['nullable', 'string', 'max:64'],
            'reference' => ['nullable', 'string', 'max:255'],
        ]);

        $customer = $this->resolveCustomer($data);
        if ($customer === null) {
            return response()->json(['message' => 'Customer not found.'], 422);
        }

        $invoiceId = $this->resolveInvoiceId($data, $customer);

        try {
            $payment = PaymentProcessor::recordGatewayPayment(
                gateway: $gateway,
                transactionId: (string) $data['transaction_id'],
                customerId: (int) $customer->id,
                invoiceId: $invoiceId,
                amount: (float) $data['amount'],
                reference: (string) ($data['reference'] ?? $data['transaction_id']),
                meta: ['webhook_payload' => $request->except(['secret'])],
            );
        } catch (\Throwable $e) {
            Log::error('payment.webhook.failed', [
                'gateway' => $gateway,
                'error' => $e->getMessage(),
            ]);

            return response()->json(['message' => 'Processing failed.'], 500);
        }

        return response()->json([
            'accepted' => true,
            'payment_id' => $payment->id,
            'receipt_number' => $payment->receipt_number,
            'duplicate' => ($payment->meta['processed_at'] ?? null) !== null
                && $payment->wasRecentlyCreated === false,
        ]);
    }

    private function verifySecret(Request $request, string $gateway): bool
    {
        $expected = (string) config("payments.gateways.{$gateway}.webhook_secret", '');
        if ($expected === '') {
            return false;
        }

        $provided = $request->header('X-Webhook-Secret')
            ?? $request->input('secret')
            ?? $request->header('X-Payment-Secret');

        return is_string($provided) && hash_equals($expected, $provided);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveCustomer(array $data): ?Customer
    {
        if (! empty($data['customer_id'])) {
            return Customer::query()->withoutGlobalScopes()->find($data['customer_id']);
        }

        if (! empty($data['customer_code'])) {
            return Customer::query()->withoutGlobalScopes()
                ->where('customer_code', $data['customer_code'])
                ->first();
        }

        if (! empty($data['phone'])) {
            $digits = preg_replace('/\D+/', '', (string) $data['phone']) ?? '';

            return Customer::query()->withoutGlobalScopes()
                ->where(function ($q) use ($data, $digits): void {
                    $q->where('phone', $data['phone']);
                    if ($digits !== '') {
                        $q->orWhere('phone', $digits);
                    }
                })
                ->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function resolveInvoiceId(array $data, Customer $customer): ?int
    {
        if (! empty($data['invoice_id'])) {
            $inv = Invoice::query()->withoutGlobalScopes()
                ->where('customer_id', $customer->id)
                ->whereKey($data['invoice_id'])
                ->first();

            return $inv?->id;
        }

        if (! empty($data['invoice_number'])) {
            $inv = Invoice::query()->withoutGlobalScopes()
                ->where('customer_id', $customer->id)
                ->where('invoice_number', $data['invoice_number'])
                ->first();

            return $inv?->id;
        }

        return null;
    }
}
