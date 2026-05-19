<?php

namespace App\Services\Payments;

use App\Http\Controllers\BkashPaymentController;
use App\Exceptions\PaymentGatewayException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Services\Payments\RocketCheckoutService;
use Illuminate\Http\RedirectResponse;

final class PublicPaymentOrchestrator
{
    public function startInvoicePayment(
        Invoice $invoice,
        float $amount,
        string $gateway,
        string $returnTo = 'bill_payment',
    ): RedirectResponse {
        $gateway = strtolower(trim($gateway));

        $bkash = app(BkashPaymentController::class);

        $customer = $invoice->customer;

        return match ($gateway) {
            PaymentGateway::BKASH => $returnTo === 'bill_payment'
                ? $bkash->initiatePublic($invoice, $amount)
                : $bkash->initiatePortal(request(), $invoice),
            PaymentGateway::SSLCOMMERZ => $this->startSslCommerz($invoice, null, $amount, $returnTo, PaymentType::PAYMENT),
            PaymentGateway::NAGAD => $this->startNagad($invoice, $customer, $amount, $returnTo, PaymentType::PAYMENT),
            PaymentGateway::ROCKET => $this->startRocket($invoice, $customer, $amount, $returnTo, PaymentType::PAYMENT),
            PaymentGateway::PIPRAPAY => $this->startPipraPay($invoice, $customer, $amount, $returnTo, PaymentType::PAYMENT),
            default => redirect()->route('bill-payment.invoice')->with('danger', 'Unknown payment method.'),
        };
    }

    public function startWalletTopup(Customer $customer, float $amount, string $gateway): RedirectResponse
    {
        $gateway = strtolower(trim($gateway));

        $bkash = app(BkashPaymentController::class);

        return match ($gateway) {
            PaymentGateway::BKASH => $bkash->initiatePublicWallet($customer, $amount),
            PaymentGateway::SSLCOMMERZ => $this->startSslCommerz(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            PaymentGateway::NAGAD => $this->startNagad(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            PaymentGateway::ROCKET => $this->startRocket(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            PaymentGateway::PIPRAPAY => $this->startPipraPay(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            default => redirect()->route('bill-payment.invoice', ['tab' => 'wallet'])
                ->with('danger', 'This payment method is not available for wallet top-up.'),
        };
    }

    private function startNagad(
        ?Invoice $invoice,
        ?Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
    ): RedirectResponse {
        if (! config('nagad.enabled')) {
            return $this->fail($invoice, $returnTo, 'Nagad is disabled.');
        }

        if ($customer === null) {
            return $this->fail($invoice, $returnTo, 'Customer not found.');
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice?->id);

        PublicCheckoutSession::put($orderId, [
            'invoice_id' => $invoice?->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => $returnTo,
            'payment_type' => $paymentType,
            'gateway' => PaymentGateway::NAGAD,
        ]);

        try {
            $checkout = NagadCheckoutService::fromConfig()->createCheckout(
                orderId: $orderId,
                amount: $amountStr,
                callbackUrl: route('nagad.callback'),
            );
        } catch (PaymentGatewayException $e) {
            PublicCheckoutSession::forget($orderId);

            return $this->fail($invoice, $returnTo, $e->getMessage());
        }

        return redirect()->away($checkout['redirect_url']);
    }

    private function startSslCommerz(
        ?Invoice $invoice,
        ?Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
    ): RedirectResponse {
        if (! config('sslcommerz.enabled')) {
            return $this->fail($invoice, $returnTo, 'SSLCommerz is disabled.');
        }

        $customer ??= $invoice?->customer;
        if ($customer === null) {
            return $this->fail($invoice, $returnTo, 'Customer not found.');
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $tranId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice?->id);

        PublicCheckoutSession::put($tranId, [
            'invoice_id' => $invoice?->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => $returnTo,
            'payment_type' => $paymentType,
            'gateway' => PaymentGateway::SSLCOMMERZ,
        ]);

        try {
            $service = SslCommerzCheckoutService::fromConfig();
            $session = $service->createSession(
                tranId: $tranId,
                amount: $amountStr,
                productName: $invoice
                    ? 'Invoice '.$invoice->invoice_number
                    : 'Wallet top-up',
                customer: [
                    'name' => $customer->name,
                    'phone' => $customer->phone ?? '01700000000',
                    'email' => $customer->email ?? 'pay@customer.local',
                    'address' => $customer->address ?? 'Bangladesh',
                ],
                successUrl: route('sslcommerz.success'),
                failUrl: route('sslcommerz.fail'),
                cancelUrl: route('sslcommerz.cancel'),
            );
        } catch (PaymentGatewayException $e) {
            PublicCheckoutSession::forget($tranId);

            return $this->fail($invoice, $returnTo, $e->getMessage());
        }

        return redirect()->away($session['redirect_url']);
    }

    private function startPipraPay(
        ?Invoice $invoice,
        ?Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
    ): RedirectResponse {
        if (! PipraPayCheckoutService::isEnabled()) {
            return $this->fail($invoice, $returnTo, 'PipraPay is disabled.');
        }

        $customer ??= $invoice?->customer;
        if ($customer === null) {
            return $this->fail($invoice, $returnTo, 'Customer not found.');
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice?->id);

        $session = [
            'invoice_id' => $invoice?->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => $returnTo,
            'payment_type' => $paymentType,
            'gateway' => PaymentGateway::PIPRAPAY,
        ];

        PublicCheckoutSession::put($orderId, $session);
        PipraPayCheckoutStore::persist($orderId, $session);

        try {
            $checkout = PipraPayCheckoutService::fromConfig()->createCharge(
                customer: $customer,
                amount: (float) $amountStr,
                orderId: $orderId,
                redirectUrl: PipraPayCheckoutService::publicUrl('/piprapay/success', ['order_id' => $orderId]),
                cancelUrl: PipraPayCheckoutService::publicUrl('/piprapay/cancel', ['order_id' => $orderId]),
                webhookUrl: PipraPayCheckoutService::publicUrl('/piprapay/webhook'),
                metadata: [
                    'invoice_id' => $invoice?->id,
                    'customer_id' => $customer->id,
                    'payment_type' => $paymentType,
                    'return_to' => $returnTo,
                    'order_id' => $orderId,
                ],
            );
        } catch (PaymentGatewayException $e) {
            PublicCheckoutSession::forget($orderId);

            return $this->fail($invoice, $returnTo, $e->getMessage());
        }

        if (filled($checkout['pp_id'] ?? null)) {
            PipraPayCheckoutStore::attachPpId($orderId, (string) $checkout['pp_id']);
        }

        return redirect()->away($checkout['redirect_url']);
    }

    private function startRocket(
        ?Invoice $invoice,
        ?Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
    ): RedirectResponse {
        if (! RocketCheckoutService::isEnabled()) {
            return $this->fail($invoice, $returnTo, 'Rocket is disabled.');
        }

        $customer ??= $invoice?->customer;
        if ($customer === null) {
            return $this->fail($invoice, $returnTo, 'Customer not found.');
        }

        return app(RocketCheckoutService::class)
            ->startCheckout($invoice, $customer, $amount, $returnTo, $paymentType)['redirect'];
    }

    private function fail(?Invoice $invoice, string $returnTo, string $message): RedirectResponse
    {
        if ($returnTo === 'bill_payment') {
            return redirect()->route('bill-payment.invoice')->with('danger', $message);
        }

        if ($returnTo === 'portal' && $invoice) {
            return redirect()->route('portal.invoices.show', $invoice)->with('danger', $message);
        }

        return redirect()->route('bill-payment.index')->with('danger', $message);
    }
}
