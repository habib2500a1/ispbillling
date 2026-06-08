<?php

namespace App\Services\Tenant;

use App\Models\PlatformInvoice;
use App\Models\Tenant;
use App\Services\Payments\PipraPayCheckoutService;
use App\Services\Payments\PublicCheckoutSession;
use App\Support\PaymentGateway;
use App\Support\PaymentType;
use App\Support\PersonalMfsGateway;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class PlatformInvoicePaymentService
{
    public function ensureToken(PlatformInvoice $invoice): PlatformInvoice
    {
        if (filled($invoice->payment_token)) {
            return $invoice;
        }

        $invoice->forceFill([
            'payment_token' => Str::lower(Str::random(48)),
        ])->save();

        return $invoice->fresh();
    }

    public function paymentUrl(PlatformInvoice $invoice): string
    {
        $invoice = $this->ensureToken($invoice);

        return route('platform-invoice.pay', ['token' => $invoice->payment_token]);
    }

    /**
     * @return array{redirect_url: string}
     */
    public function initiatePipraPay(PlatformInvoice $invoice): array
    {
        $invoice = $this->ensureToken($invoice->fresh(['tenant']));
        $this->assertPayable($invoice);

        if (! PipraPayCheckoutService::isEnabled()) {
            throw ValidationException::withMessages(['gateway' => 'PipraPay is not enabled on this server.']);
        }

        $tenant = $invoice->tenant;
        $amount = round((float) $invoice->amount, 2);
        $orderId = 'PLT-INV-'.$invoice->id.'-'.now()->format('YmdHis').'-'.substr(bin2hex(random_bytes(4)), 0, 8);

        PublicCheckoutSession::put($orderId, [
            'platform_invoice_id' => $invoice->id,
            'amount' => number_format($amount, 2, '.', ''),
            'return_to' => 'platform_invoice',
            'payment_type' => PaymentType::PLATFORM_SUBSCRIPTION,
            'gateway' => PaymentGateway::PIPRAPAY,
        ]);

        $checkout = PipraPayCheckoutService::fromConfig()->createChargeForPayer(
            fullName: $tenant?->name ?? 'ISP Tenant',
            email: $tenant?->contact_email ?: 'billing@isp.local',
            phone: $tenant?->contact_phone ?: '01700000000',
            amount: $amount,
            orderId: $orderId,
            redirectUrl: PipraPayCheckoutService::publicUrl('/piprapay/success', ['order_id' => $orderId]),
            cancelUrl: PipraPayCheckoutService::publicUrl('/piprapay/cancel', ['order_id' => $orderId]),
            webhookUrl: PipraPayCheckoutService::publicUrl('/piprapay/webhook'),
            metadata: [
                'platform_invoice_id' => $invoice->id,
                'order_id' => $orderId,
                'payment_type' => PaymentType::PLATFORM_SUBSCRIPTION,
            ],
        );

        return ['redirect_url' => $checkout['redirect_url']];
    }

    /**
     * @return array{redirect_url: string, order_id: string}
     */
    public function initiatePersonalMfs(PlatformInvoice $invoice, string $gateway): array
    {
        $invoice = $this->ensureToken($invoice->fresh(['tenant']));
        $this->assertPayable($invoice);
        $gateway = strtolower($gateway);

        if (! PersonalMfsGateway::isPersonalEnabled($gateway)) {
            throw ValidationException::withMessages(['gateway' => 'This payment method is not available.']);
        }

        $amount = round((float) $invoice->amount, 2);
        $orderId = 'PLT-MFS-'.$invoice->id.'-'.now()->format('YmdHis').'-'.substr(bin2hex(random_bytes(4)), 0, 8);

        PublicCheckoutSession::put($orderId, [
            'platform_invoice_id' => $invoice->id,
            'amount' => number_format($amount, 2, '.', ''),
            'return_to' => 'platform_invoice',
            'payment_type' => PaymentType::PLATFORM_SUBSCRIPTION,
            'gateway' => $gateway,
            'payment_token' => $invoice->payment_token,
        ]);

        return [
            'order_id' => $orderId,
            'redirect_url' => route('platform-invoice.mfs', [
                'token' => $invoice->payment_token,
                'gateway' => $gateway,
                'order' => $orderId,
            ]),
        ];
    }

    public function completePayment(
        PlatformInvoice $invoice,
        string $gateway,
        string $reference,
        ?string $orderId = null,
    ): PlatformInvoice {
        $invoice = PlatformInvoice::query()->lockForUpdate()->findOrFail($invoice->id);

        if ($invoice->isPaid()) {
            return $invoice;
        }

        return app(PlatformInvoiceBillingService::class)->markPaid($invoice, $reference, $gateway);
    }

    public function findByToken(string $token): ?PlatformInvoice
    {
        return PlatformInvoice::query()
            ->with('tenant')
            ->where('payment_token', $token)
            ->first();
    }

    private function assertPayable(PlatformInvoice $invoice): void
    {
        if ($invoice->isPaid()) {
            throw ValidationException::withMessages(['invoice' => 'This platform bill is already paid.']);
        }

        if ($invoice->status === PlatformInvoice::STATUS_VOID) {
            throw ValidationException::withMessages(['invoice' => 'This platform bill is void.']);
        }

        if ((float) $invoice->amount <= 0) {
            throw ValidationException::withMessages(['invoice' => 'Nothing to pay on this bill.']);
        }
    }
}
