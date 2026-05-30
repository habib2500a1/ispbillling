<?php

namespace App\Services\Payments;

use App\Http\Controllers\BkashPaymentController;
use App\Exceptions\PaymentGatewayException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Support\BkashSettings;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Services\Payments\RocketCheckoutService;
use App\Support\PersonalMfsGateway;
use Illuminate\Http\RedirectResponse;

final class PublicPaymentOrchestrator
{
    public function startInvoicePayment(
        Invoice $invoice,
        float $amount,
        string $gateway,
        string $returnTo = 'bill_payment',
    ): RedirectResponse {
        $resolved = PaymentGateway::resolveCheckoutSelection($gateway);
        $gateway = $resolved['gateway'];

        $customer = $invoice->customer;

        if ($gateway === PaymentGateway::BKASH) {
            return $this->routeBkashCheckout(
                $resolved['mode'],
                $invoice,
                $customer,
                $amount,
                $returnTo,
                PaymentType::PAYMENT,
            );
        }

        return match ($gateway) {
            PaymentGateway::SSLCOMMERZ => $this->startSslCommerz($invoice, null, $amount, $returnTo, PaymentType::PAYMENT),
            PaymentGateway::NAGAD => PersonalMfsGateway::nagadPersonalEnabled()
                ? $this->startPersonalMfs(PaymentGateway::NAGAD, $invoice, $customer, $amount, $returnTo, PaymentType::PAYMENT)
                : $this->startNagad($invoice, $customer, $amount, $returnTo, PaymentType::PAYMENT),
            PaymentGateway::ROCKET => $this->startRocket($invoice, $customer, $amount, $returnTo, PaymentType::PAYMENT),
            PaymentGateway::PIPRAPAY => $this->startPipraPay($invoice, $customer, $amount, $returnTo, PaymentType::PAYMENT),
            default => redirect()->route('bill-payment.invoice')->with('danger', 'Unknown payment method.'),
        };
    }

    public function startWalletTopup(Customer $customer, float $amount, string $gateway): RedirectResponse
    {
        $resolved = PaymentGateway::resolveCheckoutSelection($gateway);
        $gateway = $resolved['gateway'];

        if ($gateway === PaymentGateway::BKASH) {
            return $this->routeBkashCheckout(
                $resolved['mode'],
                null,
                $customer,
                $amount,
                'bill_payment',
                PaymentType::WALLET_DEPOSIT,
            );
        }

        return match ($gateway) {
            PaymentGateway::SSLCOMMERZ => $this->startSslCommerz(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            PaymentGateway::NAGAD => PersonalMfsGateway::nagadPersonalEnabled()
                ? $this->startPersonalMfs(PaymentGateway::NAGAD, null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT)
                : $this->startNagad(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            PaymentGateway::ROCKET => $this->startRocket(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            PaymentGateway::PIPRAPAY => $this->startPipraPay(null, $customer, $amount, 'bill_payment', PaymentType::WALLET_DEPOSIT),
            default => redirect()->route('bill-payment.invoice', ['tab' => 'wallet'])
                ->with('danger', 'This payment method is not available for wallet top-up.'),
        };
    }

    public function startPrepayPayment(
        Customer $customer,
        float $amount,
        int $months,
        string $gateway,
        string $returnTo = 'bill_payment',
    ): RedirectResponse {
        $resolved = PaymentGateway::resolveCheckoutSelection($gateway);
        $gateway = $resolved['gateway'];
        $months = max(1, $months);

        if ($gateway === PaymentGateway::BKASH) {
            return $this->routeBkashCheckout(
                $resolved['mode'],
                null,
                $customer,
                $amount,
                $returnTo,
                PaymentType::PREPAY,
                $months,
            );
        }

        return match ($gateway) {
            PaymentGateway::SSLCOMMERZ => $this->startSslCommerz(null, $customer, $amount, $returnTo, PaymentType::PREPAY, $months),
            PaymentGateway::NAGAD => PersonalMfsGateway::nagadPersonalEnabled()
                ? $this->startPersonalMfs(PaymentGateway::NAGAD, null, $customer, $amount, $returnTo, PaymentType::PREPAY, $months)
                : $this->startNagad(null, $customer, $amount, $returnTo, PaymentType::PREPAY, $months),
            PaymentGateway::ROCKET => $this->startRocket(null, $customer, $amount, $returnTo, PaymentType::PREPAY, $months),
            PaymentGateway::PIPRAPAY => $this->startPipraPay(null, $customer, $amount, $returnTo, PaymentType::PREPAY, $months),
            default => $this->fail(null, $returnTo, 'This payment method is not available for advance payment.', PaymentType::PREPAY),
        };
    }

    private function startNagad(
        ?Invoice $invoice,
        ?Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
        int $prepayMonths = 0,
    ): RedirectResponse {
        if (! config('nagad.enabled')) {
            return $this->fail($invoice, $returnTo, 'Nagad is disabled.');
        }

        if ($customer === null) {
            return $this->fail($invoice, $returnTo, 'Customer not found.');
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice?->id);

        PublicCheckoutSession::put($orderId, $this->checkoutSessionPayload(
            $customer,
            $invoice,
            $amountStr,
            $returnTo,
            $paymentType,
            PaymentGateway::NAGAD,
            $prepayMonths,
        ));

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
        int $prepayMonths = 0,
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

        PublicCheckoutSession::put($tranId, $this->checkoutSessionPayload(
            $customer,
            $invoice,
            $amountStr,
            $returnTo,
            $paymentType,
            PaymentGateway::SSLCOMMERZ,
            $prepayMonths,
        ));

        try {
            $service = SslCommerzCheckoutService::fromConfig();
            $session = $service->createSession(
                tranId: $tranId,
                amount: $amountStr,
                productName: $invoice
                    ? 'Invoice '.$invoice->invoice_number
                    : ($paymentType === PaymentType::PREPAY
                        ? 'Advance payment '.$prepayMonths.' month(s)'
                        : 'Wallet top-up'),
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
        int $prepayMonths = 0,
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

        $session = $this->checkoutSessionPayload(
            $customer,
            $invoice,
            $amountStr,
            $returnTo,
            $paymentType,
            PaymentGateway::PIPRAPAY,
            $prepayMonths,
        );

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
        int $prepayMonths = 0,
    ): RedirectResponse {
        if (! RocketCheckoutService::isEnabled()) {
            return $this->fail($invoice, $returnTo, 'Rocket is disabled.');
        }

        $customer ??= $invoice?->customer;
        if ($customer === null) {
            return $this->fail($invoice, $returnTo, 'Customer not found.');
        }

        return app(RocketCheckoutService::class)
            ->startCheckout($invoice, $customer, $amount, $returnTo, $paymentType, $prepayMonths)['redirect'];
    }

    private function routeBkashCheckout(
        ?string $mode,
        ?Invoice $invoice,
        ?Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
        int $prepayMonths = 0,
    ): RedirectResponse {
        $channel = $returnTo === 'portal' ? BkashSettings::CHANNEL_PORTAL : BkashSettings::CHANNEL_PUBLIC_PAY;

        $merchantReady = BkashSettings::isMerchantActiveForChannel($channel);
        $personalReady = BkashSettings::isPersonalActiveForChannel($channel);

        $usePersonal = match ($mode) {
            'personal' => true,
            'merchant' => ! $merchantReady && $personalReady,
            default => $personalReady && ! $merchantReady,
        };

        if ($mode === 'merchant' && ! $merchantReady && ! $personalReady) {
            return $this->fail(
                $invoice,
                $returnTo,
                BkashSettings::isMerchantEnabled()
                    ? 'bKash Merchant API credentials are missing. Add App key, secret, username and password under Admin → Payment → bKash Merchant API, then Save.'
                    : 'bKash Merchant checkout is disabled. Enable it in payment gateway settings or use bKash Personal.',
                $paymentType,
            );
        }

        if ($usePersonal) {
            if (! PersonalMfsGateway::bkashPersonalEnabled()) {
                return $this->fail($invoice, $returnTo, 'bKash Personal is not configured.', $paymentType);
            }

            return $this->startPersonalMfs(
                PaymentGateway::BKASH,
                $invoice,
                $customer,
                $amount,
                $returnTo,
                $paymentType,
                $prepayMonths,
            );
        }

        $bkash = app(BkashPaymentController::class);

        if ($paymentType === PaymentType::PREPAY) {
            return $bkash->initiatePublicPrepay($customer, $amount, $prepayMonths, $returnTo);
        }

        if ($paymentType === PaymentType::WALLET_DEPOSIT) {
            return $bkash->initiatePublicWallet($customer, $amount);
        }

        if ($returnTo === 'bill_payment' && $invoice !== null) {
            return $bkash->initiatePublic($invoice, $amount);
        }

        if ($invoice !== null) {
            return $bkash->initiatePortal(request(), $invoice);
        }

        return $this->fail($invoice, $returnTo, 'bKash Merchant checkout is not available.', $paymentType);
    }

    private function startPersonalMfs(
        string $gateway,
        ?Invoice $invoice,
        ?Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
        int $prepayMonths = 0,
    ): RedirectResponse {
        if (! PersonalMfsGateway::isPersonalEnabled($gateway)) {
            return $this->fail($invoice, $returnTo, PaymentGateway::label($gateway).' personal payment is not configured.', $paymentType);
        }

        $customer ??= $invoice?->customer;
        if ($customer === null) {
            return $this->fail($invoice, $returnTo, 'Customer not found.', $paymentType);
        }

        return app(PersonalMfsCheckoutService::class)
            ->startCheckout($gateway, $invoice, $customer, $amount, $returnTo, $paymentType, $prepayMonths)['redirect'];
    }

    /**
     * @return array<string, mixed>
     */
    private function checkoutSessionPayload(
        Customer $customer,
        ?Invoice $invoice,
        string $amount,
        string $returnTo,
        string $paymentType,
        string $gateway,
        int $prepayMonths = 0,
    ): array {
        $payload = [
            'invoice_id' => $invoice?->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amount,
            'return_to' => $returnTo,
            'payment_type' => $paymentType,
            'gateway' => $gateway,
        ];

        if ($prepayMonths > 0) {
            $payload['prepay_months'] = $prepayMonths;
        }

        return $payload;
    }

    private function fail(?Invoice $invoice, string $returnTo, string $message, ?string $paymentType = null): RedirectResponse
    {
        if ($returnTo === 'bill_payment') {
            $params = $paymentType === PaymentType::PREPAY ? ['tab' => 'prepay'] : [];

            return redirect()->route('bill-payment.invoice', $params)->with('danger', $message);
        }

        if ($returnTo === 'portal') {
            if ($invoice) {
                return redirect()->route('portal.invoices.show', $invoice)->with('danger', $message);
            }

            return redirect()->route('portal.bills.index')->with('danger', $message);
        }

        return redirect()->route('bill-payment.index')->with('danger', $message);
    }
}
