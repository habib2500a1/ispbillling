<?php

namespace App\Services\Payments;

use App\Models\Customer;
use App\Models\Invoice;
use App\Support\PersonalMfsGateway;

final class PersonalMfsCheckoutService
{
    public static function isEnabled(string $gateway): bool
    {
        return PersonalMfsGateway::isPersonalEnabled($gateway);
    }

    /**
     * @return array{order_id: string, redirect: \Illuminate\Http\RedirectResponse}
     */
    public function startCheckout(
        string $gateway,
        ?Invoice $invoice,
        Customer $customer,
        float $amount,
        string $returnTo,
        string $paymentType,
    ): array {
        $amountStr = number_format(max(0.01, $amount), 2, '.', '');
        $orderId = PublicCheckoutSession::makeTranId((int) $customer->id, $invoice?->id);

        PublicCheckoutSession::put($orderId, [
            'invoice_id' => $invoice?->id,
            'customer_id' => (int) $customer->id,
            'amount' => $amountStr,
            'return_to' => $returnTo,
            'payment_type' => $paymentType,
            'gateway' => $gateway,
        ]);

        return [
            'order_id' => $orderId,
            'redirect' => redirect()->route('mfs.personal.checkout', [
                'gateway' => $gateway,
                'order' => $orderId,
            ]),
        ];
    }
}
