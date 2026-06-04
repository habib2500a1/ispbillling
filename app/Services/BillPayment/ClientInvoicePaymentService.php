<?php

namespace App\Services\BillPayment;

use App\Http\Controllers\BkashPaymentController;
use App\Models\Customer;
use App\Models\Invoice;
use App\Services\Billing\CustomerPrepayService;
use App\Services\Mobile\CustomerMobileService;
use App\Services\Payments\NagadCheckoutService;
use App\Services\Payments\PersonalMfsCheckoutService;
use App\Services\Payments\PipraPayCheckoutService;
use App\Services\Payments\PipraPayCheckoutStore;
use App\Services\Payments\PublicCheckoutSession;
use App\Services\Payments\RocketCheckoutService;
use App\Services\Payments\SslCommerzCheckoutService;
use App\Support\CustomerBalanceDue;
use App\Support\BkashSettings;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Support\PersonalMfsGateway;
use App\Support\PortalPaymentGateways;
use App\Support\ResellerBranding;
use App\Services\Reseller\ResellerPaymentContext;
use Illuminate\Validation\ValidationException;

/**
 * Customer self-pay: show all dues, full invoice amount only (no manual partial), wallet top-up separate.
 */
class ClientInvoicePaymentService
{
    public function __construct(
        private readonly PublicBillPaymentService $publicBills,
        private readonly CustomerMobileService $mobile,
        private readonly CustomerPrepayService $prepay,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function payablesPayload(Customer $customer): array
    {
        $customer->loadMissing('package');
        $invoices = $this->publicBills->payableInvoices($customer);
        $totalDue = $this->publicBills->totalDue($customer);
        $gateways = PortalPaymentGateways::forCustomerPortal($customer);

        return [
            'total_due' => $totalDue,
            'wallet_balance' => round((float) $customer->account_balance, 2),
            'can_pay_online' => $totalDue > 0 && ($gateways['any'] ?? false),
            'require_full_payment' => ! config('bill_payment.allow_partial', false),
            'line_on_when_due_cleared' => true,
            'message' => $totalDue > 0
                ? 'Pay each invoice in full. Your line turns on automatically when all dues are cleared.'
                : 'No due invoices. Pay months in advance below or use wallet credit.',
            'gateways' => $gateways,
            'branding' => ResellerBranding::mobileBrandingPayload($customer),
            'due_invoices' => $invoices
                ->map(fn (Invoice $inv) => $this->mobile->invoiceSummary($inv))
                ->values()
                ->all(),
            'prepay' => $this->prepayPayload($customer, $gateways),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function prepayPayload(Customer $customer, ?array $gateways = null): ?array
    {
        if (! $this->prepay->isEnabled()) {
            return null;
        }

        $monthly = $this->prepay->monthlyRate($customer);
        if ($monthly === null) {
            return null;
        }

        $gateways ??= PortalPaymentGateways::forCustomerPortal($customer);
        $quickMonths = array_values(array_filter(
            $this->prepay->quickMonthOptions(),
            fn (int $m): bool => $m >= 1 && $m <= 3,
        ));

        if ($quickMonths === []) {
            $quickMonths = [1, 2, 3];
        }

        $quotes = [];
        foreach ($quickMonths as $months) {
            $quote = $this->prepay->quote($customer, $months);
            if ($quote === null) {
                continue;
            }
            $quotes[(string) $months] = $this->formatPrepayQuote($quote);
        }

        if ($quotes === []) {
            return null;
        }

        $customer->loadMissing('package');

        return [
            'enabled' => true,
            'monthly_rate' => $monthly,
            'package_name' => $customer->package?->name,
            'can_pay_online' => (bool) ($gateways['any'] ?? false),
            'quick_months' => array_map('intval', array_keys($quotes)),
            'quotes' => $quotes,
        ];
    }

    /**
     * @param  array<string, mixed>  $quote
     * @return array<string, mixed>
     */
    private function formatPrepayQuote(array $quote): array
    {
        $expires = $quote['projected_expires_at'] ?? null;

        return [
            'months' => (int) $quote['months'],
            'monthly_rate' => (float) $quote['monthly_rate'],
            'current_due' => (float) $quote['current_due'],
            'prepay_amount' => (float) $quote['prepay_amount'],
            'total_amount' => (float) $quote['total_amount'],
            'projected_expires_at' => $expires instanceof \Carbon\CarbonInterface
                ? $expires->toDateString()
                : (is_string($expires) ? $expires : null),
        ];
    }

    /**
     * @return array{payment_url: string, amount: string, gateway: string, months: int, quote: array<string, mixed>}
     */
    public function prepareMobilePrepay(Customer $customer, int $months, string $gateway): array
    {
        return ResellerPaymentContext::usingCustomer($customer, function () use ($customer, $months, $gateway): array {
            $quote = $this->prepay->assertQuote($customer, $months);
            $amount = (float) $quote['total_amount'];
            $gateway = strtolower(trim($gateway));

            $enabled = PortalPaymentGateways::forCustomerPortal($customer);
            if (! ($enabled[$gateway] ?? false)) {
                throw ValidationException::withMessages(['gateway' => 'This payment method is not available.']);
            }

            $result = match ($gateway) {
                PaymentGateway::BKASH => $this->prepareBkashPrepay($customer, $amount, $months),
                PaymentGateway::SSLCOMMERZ => $this->prepareSslCommerzPrepay($customer, $amount, $months),
                PaymentGateway::NAGAD => $this->prepareNagadPrepay($customer, $amount, $months),
                PaymentGateway::ROCKET => $this->prepareRocketPrepay($customer, $amount, $months),
                PaymentGateway::PIPRAPAY => $this->preparePipraPayPrepay($customer, $amount, $months),
                default => ['error' => 'Unknown payment method.'],
            };

            if (isset($result['error'])) {
                throw ValidationException::withMessages(['gateway' => (string) $result['error']]);
            }

            return [
                'payment_url' => $result['payment_url'],
                'amount' => $result['amount'],
                'gateway' => $gateway,
                'months' => $months,
                'quote' => $this->formatPrepayQuote($quote),
            ];
        });
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareBkashPrepay(Customer $customer, float $amount, int $months): array
    {
        if (PersonalMfsGateway::bkashPersonalEnabled()
            && ! BkashSettings::isMerchantActiveForChannel(BkashSettings::CHANNEL_PORTAL)) {
            return $this->preparePersonalMfsPrepay(PaymentGateway::BKASH, $customer, $amount, $months);
        }

        $result = app(BkashPaymentController::class)->prepareMobilePrepay($customer, $amount, $months);

        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        return [
            'payment_url' => $result['bkash_url'],
            'amount' => $result['amount'],
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareSslCommerzPrepay(Customer $customer, float $amount, int $months): array
    {
        if (! config('sslcommerz.enabled')) {
            return ['error' => 'SSLCommerz is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $tranId = PublicCheckoutSession::makeTranId((int) $customer->id, null);

        PublicCheckoutSession::put($tranId, [
            'invoice_id' => null,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => 'portal',
            'payment_type' => PaymentType::PREPAY,
            'gateway' => PaymentGateway::SSLCOMMERZ,
            'prepay_months' => max(1, $months),
        ]);

        try {
            $session = SslCommerzCheckoutService::fromConfig()->createSession(
                tranId: $tranId,
                amount: $amountStr,
                productName: 'Advance payment '.$months.' month(s)',
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
        } catch (\Throwable $e) {
            PublicCheckoutSession::forget($tranId);

            return ['error' => $e->getMessage()];
        }

        return [
            'payment_url' => $session['redirect_url'],
            'amount' => $amountStr,
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareNagadPrepay(Customer $customer, float $amount, int $months): array
    {
        if (PersonalMfsGateway::nagadPersonalEnabled()) {
            return $this->preparePersonalMfsPrepay(PaymentGateway::NAGAD, $customer, $amount, $months);
        }

        if (! config('nagad.enabled')) {
            return ['error' => 'Nagad is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, null);

        PublicCheckoutSession::put($orderId, [
            'invoice_id' => null,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => 'portal',
            'payment_type' => PaymentType::PREPAY,
            'gateway' => PaymentGateway::NAGAD,
            'prepay_months' => max(1, $months),
        ]);

        try {
            $checkout = NagadCheckoutService::fromConfig()->createCheckout(
                orderId: $orderId,
                amount: $amountStr,
                callbackUrl: route('nagad.callback'),
            );
        } catch (\Throwable $e) {
            PublicCheckoutSession::forget($orderId);

            return ['error' => $e->getMessage()];
        }

        return [
            'payment_url' => $checkout['redirect_url'],
            'amount' => $amountStr,
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareRocketPrepay(Customer $customer, float $amount, int $months): array
    {
        if (! config('rocket.enabled')) {
            return ['error' => 'Rocket is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');

        $started = app(RocketCheckoutService::class)->startCheckout(
            null,
            $customer,
            (float) $amountStr,
            'portal',
            PaymentType::PREPAY,
            max(1, $months),
        );

        return [
            'payment_url' => route('rocket.checkout', ['order' => $started['order_id']]),
            'amount' => $amountStr,
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function preparePipraPayPrepay(Customer $customer, float $amount, int $months): array
    {
        if (! PipraPayCheckoutService::isEnabled()) {
            return ['error' => 'PipraPay is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, null);

        $session = [
            'invoice_id' => null,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => 'portal',
            'payment_type' => PaymentType::PREPAY,
            'gateway' => PaymentGateway::PIPRAPAY,
            'prepay_months' => max(1, $months),
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
                metadata: [
                    'customer_id' => $customer->id,
                    'prepay_months' => max(1, $months),
                ],
            );
        } catch (\Throwable $e) {
            PublicCheckoutSession::forget($orderId);
            PipraPayCheckoutStore::forget($orderId);

            return ['error' => $e->getMessage()];
        }

        $url = $checkout['payment_url'] ?? $checkout['pp_url'] ?? null;
        if (! is_string($url) || $url === '') {
            return ['error' => 'PipraPay did not return a checkout URL.'];
        }

        return [
            'payment_url' => $url,
            'amount' => $amountStr,
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function preparePersonalMfsPrepay(string $gateway, Customer $customer, float $amount, int $months): array
    {
        $started = app(PersonalMfsCheckoutService::class)->startCheckout(
            $gateway,
            null,
            $customer,
            $amount,
            'portal',
            PaymentType::PREPAY,
            max(1, $months),
        );

        return [
            'payment_url' => route('mfs.personal.checkout', [
                'gateway' => $gateway,
                'order' => $started['order_id'],
            ]),
            'amount' => number_format(max(0.01, $amount), 2, '.', ''),
        ];
    }

    /**
     * @return array{payment_url: string, amount: string, gateway: string, invoice_id: int}
     */
    public function prepareMobilePayment(Invoice $invoice, string $gateway): array
    {
        $invoice->loadMissing('customer');
        $customer = $invoice->customer;
        if ($customer === null) {
            throw ValidationException::withMessages(['invoice' => 'Customer not found.']);
        }

        return ResellerPaymentContext::usingCustomer($customer, function () use ($invoice, $customer, $gateway): array {
            $balance = round($invoice->balanceDue(), 2);
            if ($balance <= 0 || in_array($invoice->status, ['void', 'cancelled', 'paid'], true)) {
                throw ValidationException::withMessages(['invoice' => 'This invoice has nothing to pay.']);
            }

            $amount = $this->requiredPaymentAmount($invoice, null);
            $gateway = strtolower(trim($gateway));

            $enabled = PortalPaymentGateways::forCustomerPortal($customer);
            if (! ($enabled[$gateway] ?? false)) {
                throw ValidationException::withMessages(['gateway' => 'This payment method is not available.']);
            }

            $result = match ($gateway) {
                PaymentGateway::BKASH => $this->prepareBkash($invoice, $amount),
                PaymentGateway::SSLCOMMERZ => $this->prepareSslCommerz($invoice, $customer, $amount),
                PaymentGateway::NAGAD => $this->prepareNagad($invoice, $customer, $amount),
                PaymentGateway::ROCKET => $this->prepareRocket($invoice, $customer, $amount),
                PaymentGateway::PIPRAPAY => $this->preparePipraPay($invoice, $customer, $amount),
                default => ['error' => 'Unknown payment method.'],
            };

            if (isset($result['error'])) {
                throw ValidationException::withMessages(['gateway' => (string) $result['error']]);
            }

            return [
                'payment_url' => $result['payment_url'],
                'amount' => $result['amount'],
                'gateway' => $gateway,
                'invoice_id' => (int) $invoice->id,
                'balance_due' => $balance,
            ];
        });
    }

    public function requiredPaymentAmount(Invoice $invoice, ?float $requested): float
    {
        $balance = round($invoice->balanceDue(), 2);
        if ($balance <= 0) {
            return 0.0;
        }

        if ($requested !== null && config('bill_payment.allow_partial', false)) {
            $min = (float) config('bill_payment.min_amount', 10);

            return round(min(max($min, $requested), $balance), 2);
        }

        return $balance;
    }

    public function assertCustomerOwnsInvoice(Customer $customer, Invoice $invoice): void
    {
        if ((int) $invoice->customer_id !== (int) $customer->id) {
            abort(404);
        }
    }

    public function refreshDueAfterPayment(Customer $customer): void
    {
        CustomerBalanceDue::refreshMetaAfterPayment($customer->fresh() ?? $customer);
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareBkash(Invoice $invoice, float $amount): array
    {
        if (PersonalMfsGateway::bkashPersonalEnabled()
            && ! BkashSettings::isMerchantActiveForChannel(BkashSettings::CHANNEL_PORTAL)) {
            return $this->personalMfsUrl(PaymentGateway::BKASH, $invoice, $invoice->customer, $amount);
        }

        if (! BkashSettings::isMerchantActiveForChannel(BkashSettings::CHANNEL_PORTAL)) {
            if (PersonalMfsGateway::bkashPersonalEnabled()) {
                return $this->personalMfsUrl(PaymentGateway::BKASH, $invoice, $invoice->customer, $amount);
            }

            return ['error' => 'bKash is not available. Enable Personal bKash or add Merchant API credentials in admin.'];
        }

        $result = app(BkashPaymentController::class)->prepareMobileCheckout($invoice, $amount);

        if (isset($result['error'])) {
            return ['error' => $result['error']];
        }

        return [
            'payment_url' => $result['bkash_url'],
            'amount' => $result['amount'],
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareSslCommerz(Invoice $invoice, Customer $customer, float $amount): array
    {
        if (! config('sslcommerz.enabled')) {
            return ['error' => 'SSLCommerz is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $tranId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice->id);

        PublicCheckoutSession::put($tranId, [
            'invoice_id' => $invoice->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => 'portal',
            'payment_type' => PaymentType::PAYMENT,
            'gateway' => PaymentGateway::SSLCOMMERZ,
        ]);

        try {
            $session = SslCommerzCheckoutService::fromConfig()->createSession(
                tranId: $tranId,
                amount: $amountStr,
                productName: 'Invoice '.$invoice->invoice_number,
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
        } catch (\Throwable $e) {
            PublicCheckoutSession::forget($tranId);

            return ['error' => $e->getMessage()];
        }

        return [
            'payment_url' => $session['redirect_url'],
            'amount' => $amountStr,
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareNagad(Invoice $invoice, Customer $customer, float $amount): array
    {
        if (PersonalMfsGateway::nagadPersonalEnabled()) {
            return $this->personalMfsUrl(PaymentGateway::NAGAD, $invoice, $customer, $amount);
        }

        if (! config('nagad.enabled')) {
            return ['error' => 'Nagad is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice->id);

        PublicCheckoutSession::put($orderId, [
            'invoice_id' => $invoice->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => 'portal',
            'payment_type' => PaymentType::PAYMENT,
            'gateway' => PaymentGateway::NAGAD,
        ]);

        try {
            $checkout = NagadCheckoutService::fromConfig()->createCheckout(
                orderId: $orderId,
                amount: $amountStr,
                callbackUrl: route('nagad.callback'),
            );
        } catch (\Throwable $e) {
            PublicCheckoutSession::forget($orderId);

            return ['error' => $e->getMessage()];
        }

        return [
            'payment_url' => $checkout['redirect_url'],
            'amount' => $amountStr,
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function prepareRocket(Invoice $invoice, Customer $customer, float $amount): array
    {
        if (! config('rocket.enabled')) {
            return ['error' => 'Rocket is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');

        $started = app(RocketCheckoutService::class)->startCheckout(
            $invoice,
            $customer,
            (float) $amountStr,
            'portal',
            PaymentType::PAYMENT,
        );

        return [
            'payment_url' => route('rocket.checkout', ['order' => $started['order_id']]),
            'amount' => $amountStr,
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}
     */
    private function personalMfsUrl(string $gateway, Invoice $invoice, Customer $customer, float $amount): array
    {
        $started = app(PersonalMfsCheckoutService::class)->startCheckout(
            $gateway,
            $invoice,
            $customer,
            $amount,
            'portal',
            PaymentType::PAYMENT,
        );

        return [
            'payment_url' => route('mfs.personal.checkout', [
                'gateway' => $gateway,
                'order' => $started['order_id'],
            ]),
            'amount' => number_format(max(0.01, $amount), 2, '.', ''),
        ];
    }

    /**
     * @return array{payment_url: string, amount: string}|array{error: string}
     */
    private function preparePipraPay(Invoice $invoice, Customer $customer, float $amount): array
    {
        if (! PipraPayCheckoutService::isEnabled()) {
            return ['error' => 'PipraPay is disabled.'];
        }

        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice->id);

        $session = [
            'invoice_id' => $invoice->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => 'portal',
            'payment_type' => PaymentType::PAYMENT,
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
                metadata: ['invoice_id' => $invoice->id],
            );
        } catch (\Throwable $e) {
            PublicCheckoutSession::forget($orderId);
            PipraPayCheckoutStore::forget($orderId);

            return ['error' => $e->getMessage()];
        }

        $url = $checkout['payment_url'] ?? $checkout['pp_url'] ?? null;
        if (! is_string($url) || $url === '') {
            return ['error' => 'PipraPay did not return a checkout URL.'];
        }

        return [
            'payment_url' => $url,
            'amount' => $amountStr,
        ];
    }
}
