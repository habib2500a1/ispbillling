<?php

namespace App\Http\Controllers;

use App\Exceptions\BkashApiException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Payments\BkashCheckoutService;
use App\Support\BkashSettings;
use App\Support\CheckoutPaymentMeta;
use App\Support\PaymentType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BkashPaymentController extends Controller
{
    private const CACHE_PREFIX = 'bkash_checkout:';

    private const CACHE_TTL_SECONDS = 3600;

    /**
     * @return array{bkash_url: string, payment_id: string, amount: string}|array{error: string}
     */
    public function prepareMobileCheckout(Invoice $invoice, ?float $amount = null): array
    {
        if (! BkashSettings::isMerchantActiveForChannel(BkashSettings::CHANNEL_PORTAL)) {
            return ['error' => 'bKash checkout is disabled or not configured.'];
        }

        $invoice->loadMissing('customer');
        $balance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        if ($balance <= 0) {
            return ['error' => 'Nothing to pay on this invoice.'];
        }

        $payAmount = $amount !== null ? round(min($amount, $balance), 2) : $balance;

        return $this->prepareCheckout(
            customerId: (int) $invoice->customer_id,
            amount: $payAmount,
            invoice: $invoice,
            returnTo: 'portal',
            paymentType: PaymentType::PAYMENT,
        );
    }

    /**
     * @return array{bkash_url: string, payment_id: string, amount: string}|array{error: string}
     */
    private function prepareCheckout(
        int $customerId,
        float $amount,
        ?Invoice $invoice,
        string $returnTo,
        string $paymentType,
        int $prepayMonths = 0,
    ): array {
        if ($invoice !== null && in_array($invoice->status, ['void', 'cancelled'], true)) {
            return ['error' => 'This invoice cannot be paid.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');

        if (! BkashSettings::isConfigured()) {
            return [
                'error' => BkashSettings::isMerchantEnabled()
                    ? 'bKash Merchant API credentials are incomplete. In admin, open Payment → bKash Merchant API, enter App key, App secret, Username, Password, then Save and Test connection.'
                    : 'bKash Merchant API is not set up on this server.',
            ];
        }

        $service = BkashCheckoutService::fromConfig();

        try {
            $service->assertConfigured();
            $token = $service->grantToken();
            $merchantRef = $invoice
                ? $this->sanitizeMerchantInvoiceNumber((string) $invoice->invoice_number, $invoice->id)
                : ($paymentType === PaymentType::PREPAY
                    ? 'PREPAY-'.$customerId.'-'.$prepayMonths.'-'.now()->format('YmdHis')
                    : 'WALLET-'.$customerId.'-'.now()->format('YmdHis'));
            $customer = Customer::query()->withoutGlobalScopes()->find($customerId);
            $payerRef = $this->payerReferenceForCustomer($customer);

            $created = $service->createCheckoutPayment(
                $token,
                $amountStr,
                $merchantRef,
                $payerRef,
                $this->callbackBaseUrl(),
            );
        } catch (BkashApiException $e) {
            return ['error' => $e->getMessage()];
        }

        $paymentId = $created['paymentID'];

        $cachePayload = [
            'invoice_id' => $invoice?->id,
            'customer_id' => $customerId,
            'amount' => $amountStr,
            'return_to' => $returnTo,
            'payment_type' => $paymentType,
        ];

        if ($prepayMonths > 0) {
            $cachePayload['prepay_months'] = $prepayMonths;
        }

        Cache::put(
            self::CACHE_PREFIX.$paymentId,
            $cachePayload,
            self::CACHE_TTL_SECONDS,
        );

        return [
            'bkash_url' => $created['bkashURL'],
            'payment_id' => $paymentId,
            'amount' => $amountStr,
        ];
    }

    public function initiatePortal(Request $request, Invoice $invoice): RedirectResponse
    {
        $customer = $request->user('customer');
        abort_unless($customer !== null && (int) $invoice->customer_id === (int) $customer->getAuthIdentifier(), 404);

        return $this->startCheckout($invoice, 'portal');
    }

    public function initiatePublic(Invoice $invoice, float $amount): RedirectResponse
    {
        $customerId = session('bill_pay.customer_id');
        $verified = session('bill_pay.verified');
        abort_unless($customerId && $verified && (int) $invoice->customer_id === (int) $customerId, 403);

        return $this->startCheckout($invoice, 'bill_payment', $amount);
    }

    public function initiatePublicWallet(Customer $customer, float $amount): RedirectResponse
    {
        abort_unless(
            session('bill_pay.customer_id') == $customer->id && session('bill_pay.verified'),
            403
        );

        if (! config('bill_payment.wallet_topup_enabled', true)) {
            return redirect()->route('bill-payment.invoice')
                ->with('danger', 'Wallet top-up is not available.');
        }

        return $this->startWalletCheckout($customer, $amount, 'bill_payment');
    }

    public function initiatePublicPrepay(Customer $customer, float $amount, int $months, string $returnTo = 'bill_payment'): RedirectResponse
    {
        if ($returnTo === 'bill_payment') {
            abort_unless(
                session('bill_pay.customer_id') == $customer->id && session('bill_pay.verified'),
                403
            );
        }

        if (! config('bill_payment.prepay_enabled', true)) {
            return $this->prepayFailRedirect($returnTo, 'Advance payment is not available.');
        }

        $channel = $this->bkashChannelForReturnTo($returnTo);
        if (! BkashSettings::isMerchantActiveForChannel($channel)) {
            $message = BkashSettings::isMerchantEnabled()
                ? 'bKash Merchant API credentials are missing or invalid. Save them under Payment → bKash Merchant API.'
                : 'bKash Merchant checkout is disabled. Enable it in Payment gateway settings.';

            return $this->prepayFailRedirect($returnTo, $message);
        }

        $prepared = $this->prepareCheckout(
            customerId: (int) $customer->id,
            amount: round($amount, 2),
            invoice: null,
            returnTo: $returnTo,
            paymentType: PaymentType::PREPAY,
            prepayMonths: max(1, $months),
        );

        if (isset($prepared['error'])) {
            return $this->prepayFailRedirect($returnTo, $prepared['error']);
        }

        return redirect()->away($prepared['bkash_url']);
    }

    private function prepayFailRedirect(string $returnTo, string $message): RedirectResponse
    {
        if ($returnTo === 'portal') {
            return redirect()->route('portal.bills.index')->with('danger', $message);
        }

        return redirect()->route('bill-payment.invoice', ['tab' => 'prepay'])->with('danger', $message);
    }

    public function initiate(Request $request, Invoice $invoice): RedirectResponse
    {
        return $this->startCheckout($invoice, 'admin');
    }

    private function startCheckout(Invoice $invoice, string $returnTo, ?float $customAmount = null): RedirectResponse
    {
        $channel = $this->bkashChannelForReturnTo($returnTo);
        if (! BkashSettings::isMerchantActiveForChannel($channel)) {
            $message = BkashSettings::isMerchantEnabled()
                ? 'bKash Merchant API credentials are missing or invalid. Save them under Payment → bKash Merchant API.'
                : 'bKash Merchant checkout is disabled. Enable it in Payment gateway settings.';

            return $this->failRedirect($invoice, $message, $returnTo);
        }

        $invoice->loadMissing('customer');
        $balance = round((float) $invoice->total - (float) $invoice->amount_paid, 2);
        if ($balance <= 0) {
            return $this->failRedirect($invoice, 'Nothing to pay on this invoice.', $returnTo);
        }

        $min = (float) config('bill_payment.min_amount', 10);
        $amount = $customAmount ?? $balance;
        $amount = round(min(max($min, $amount), $balance), 2);

        if ($customAmount !== null && ! config('bill_payment.allow_partial', true)) {
            $amount = $balance;
        }

        $prepared = $this->prepareCheckout(
            customerId: (int) $invoice->customer_id,
            amount: $amount,
            invoice: $invoice,
            returnTo: $returnTo,
            paymentType: PaymentType::PAYMENT,
        );

        if (isset($prepared['error'])) {
            return $this->failRedirect($invoice, $prepared['error'], $returnTo);
        }

        return redirect()->away($prepared['bkash_url']);
    }

    private function startWalletCheckout(Customer $customer, float $amount, string $returnTo): RedirectResponse
    {
        $channel = $this->bkashChannelForReturnTo($returnTo);
        if (! BkashSettings::isMerchantActiveForChannel($channel)) {
            $message = BkashSettings::isMerchantEnabled()
                ? 'bKash Merchant API credentials are missing or invalid. Save them under Payment → bKash Merchant API.'
                : 'bKash Merchant checkout is disabled. Enable it in Payment gateway settings.';

            return redirect()->route('bill-payment.invoice')
                ->with('danger', $message);
        }

        $min = (float) config('bill_payment.wallet_topup_min', 100);
        $amount = round(max($min, $amount), 2);

        $prepared = $this->prepareCheckout(
            customerId: (int) $customer->id,
            amount: $amount,
            invoice: null,
            returnTo: $returnTo,
            paymentType: PaymentType::WALLET_DEPOSIT,
        );

        if (isset($prepared['error'])) {
            return redirect()->route('bill-payment.invoice')->with('danger', $prepared['error']);
        }

        return redirect()->away($prepared['bkash_url']);
    }

    public function callback(Request $request): RedirectResponse
    {
        $paymentId = $request->query('paymentID');
        $status = $request->query('status');

        if (! is_string($paymentId) || $paymentId === '') {
            return redirect()->route('bill-payment.index')
                ->with('danger', 'bKash callback missing payment ID.');
        }

        $pending = Cache::get(self::CACHE_PREFIX.$paymentId);
        if (! is_array($pending) || ! isset($pending['amount'], $pending['customer_id'])) {
            return redirect()->route('bill-payment.index')
                ->with('danger', 'bKash session expired. Please try again.');
        }

        $invoice = isset($pending['invoice_id']) ? Invoice::query()->find($pending['invoice_id']) : null;
        $paymentType = (string) ($pending['payment_type'] ?? PaymentType::PAYMENT);
        $returnTo = $pending['return_to'] ?? 'admin';

        if ($status !== 'success') {
            Cache::forget(self::CACHE_PREFIX.$paymentId);

            return $this->failRedirect($invoice, 'bKash payment was not completed.', $returnTo);
        }

        $existingQuery = Payment::query()
            ->where('meta->bkash_payment_id', $paymentId)
            ->where('status', 'completed');

        if ($invoice) {
            $existingQuery->where('invoice_id', $invoice->id);
        } else {
            $existingQuery->where('customer_id', $pending['customer_id'])
                ->where('payment_type', $paymentType);
        }

        $existing = $existingQuery->first();

        if ($existing) {
            Cache::forget(self::CACHE_PREFIX.$paymentId);

            return $this->successRedirect($invoice, $pending, 'Payment was already recorded.', $existing);
        }

        $service = BkashCheckoutService::fromConfig();

        try {
            $service->assertConfigured();
            $token = $service->grantToken();
            $executed = $service->executePayment($token, $paymentId);
        } catch (BkashApiException $e) {
            Log::warning('bKash execute failed', ['payment_id' => $paymentId, 'error' => $e->getMessage()]);

            return $this->failRedirect($invoice, $e->getMessage(), $returnTo);
        }

        if (($executed['transactionStatus'] ?? '') !== 'Completed') {
            return $this->failRedirect($invoice, 'bKash did not complete the payment.', $returnTo);
        }

        $paidAmount = (string) ($executed['amount'] ?? $pending['amount']);
        if (abs((float) $paidAmount - (float) $pending['amount']) > 0.02) {
            return $this->failRedirect($invoice, 'Paid amount mismatch. Contact support.', $returnTo);
        }

        $trxId = is_string($executed['trxID'] ?? null) ? $executed['trxID'] : $paymentId;
        $raw = $executed['raw'];

        $payment = DB::transaction(function () use ($pending, $invoice, $paymentId, $paidAmount, $trxId, $raw, $paymentType): Payment {
            return Payment::createTrusted([
                'customer_id' => (int) $pending['customer_id'],
                'invoice_id' => $invoice?->id,
                'amount' => $paidAmount,
                'method' => 'bkash',
                'gateway' => 'bkash',
                'gateway_transaction_id' => $trxId,
                'reference' => $trxId,
                'status' => 'completed',
                'paid_at' => now(),
                'payment_type' => $paymentType,
                'meta' => CheckoutPaymentMeta::fromSession($pending, [
                    'bkash_payment_id' => $paymentId,
                    'bkash_trx_id' => $trxId,
                    'bkash_execute' => $raw,
                    'source' => 'bkash_callback',
                ]),
            ]);
        });

        Cache::forget(self::CACHE_PREFIX.$paymentId);

        $message = match ($paymentType) {
            PaymentType::WALLET_DEPOSIT => 'Wallet top-up recorded successfully.',
            PaymentType::PREPAY => 'Advance payment recorded successfully.',
            default => 'bKash payment recorded successfully.',
        };

        return $this->successRedirect($invoice, $pending, $message, $payment);
    }

    /**
     * @param  array<string, mixed>  $pending
     */
    private function successRedirect(?Invoice $invoice, array $pending, string $message, ?Payment $payment = null): RedirectResponse
    {
        $returnTo = $pending['return_to'] ?? 'admin';

        if ($returnTo === 'portal' && $invoice) {
            return redirect()->route('portal.invoices.show', $invoice)->with('status', $message);
        }

        if ($returnTo === 'bill_payment') {
            $payment ??= Payment::query()
                ->where('customer_id', $pending['customer_id'])
                ->where('status', 'completed')
                ->latest('id')
                ->first();

            if ($payment) {
                return redirect()->route('bill-payment.receipt', $payment)->with('status', $message);
            }

            return redirect()->route('bill-payment.invoice')->with('status', $message);
        }

        if ($invoice) {
            return redirect()->route('filament.admin.resources.invoices.edit', ['record' => $invoice])
                ->with('success', $message);
        }

        return redirect()->route('filament.admin.resources.invoices.index')->with('success', $message);
    }

    private function failRedirect(?Invoice $invoice, string $message, ?string $returnTo = null): RedirectResponse
    {
        if ($returnTo === 'portal' && $invoice) {
            return redirect()->route('portal.invoices.show', $invoice)->with('danger', $message);
        }

        if ($returnTo === 'bill_payment') {
            return redirect()->route('bill-payment.invoice')->with('danger', $message);
        }

        if ($invoice) {
            return redirect()->route('filament.admin.resources.invoices.edit', ['record' => $invoice])
                ->with('danger', $message);
        }

        return redirect()->route('bill-payment.index')->with('danger', $message);
    }

    private function bkashChannelForReturnTo(string $returnTo): string
    {
        return match ($returnTo) {
            'bill_payment' => BkashSettings::CHANNEL_PUBLIC_PAY,
            'portal' => BkashSettings::CHANNEL_PORTAL,
            default => BkashSettings::CHANNEL_ADMIN,
        };
    }

    private function callbackBaseUrl(): string
    {
        return BkashSettings::callbackUrl();
    }

    private function payerReferenceFor(Invoice $invoice): string
    {
        return $this->payerReferenceForCustomer($invoice->customer);
    }

    private function payerReferenceForCustomer(?Customer $customer): string
    {
        $phone = $customer?->phone;
        $digits = is_string($phone) ? preg_replace('/\D+/', '', $phone) : '';

        if ($digits !== '' && strlen($digits) <= 255) {
            return $digits;
        }

        return 'cust'.($customer?->id ?? '0');
    }

    private function sanitizeMerchantInvoiceNumber(string $number, int $invoiceId): string
    {
        $number = preg_replace('/[^A-Za-z0-9\-_]/', '-', $number) ?? $number;
        $number = trim((string) $number, '-');

        return substr($number !== '' ? $number : 'INV-'.$invoiceId, 0, 255);
    }
}
