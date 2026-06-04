<?php

namespace App\Services\Reseller;

use App\Models\Customer;
use App\Models\Reseller;
use App\Support\BkashSettings;
use App\Support\PaymentGateway;
use App\Support\PortalPaymentGateways;
use App\Support\PublicPaymentMethod;

final class ResellerPaymentContext
{
    public static function hasOwnPayment(Customer $customer): bool
    {
        return self::resellerForCustomer($customer) !== null;
    }

    public static function resellerForCustomer(?Customer $customer): ?Reseller
    {
        return ResellerIntegrationSettings::resellerForCustomer($customer);
    }

    /**
     * @param  callable(): mixed  $callback
     */
    public static function usingCustomer(?Customer $customer, callable $callback): mixed
    {
        $reseller = self::resellerForCustomer($customer);
        if ($reseller === null) {
            return $callback();
        }

        return ResellerScopedConfig::using((int) $reseller->id, function () use ($callback, $reseller): mixed {
            self::normalizePaymentRuntime($reseller);
            config(['reseller_payment.active' => (int) $reseller->id]);

            try {
                return $callback();
            } finally {
                config(['reseller_payment.active' => null]);
            }
        });
    }

    /**
     * @return list<array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string, checkout?: string}>
     */
    public static function paymentMethods(Customer $customer, ?string $bkashChannel = BkashSettings::CHANNEL_PORTAL): array
    {
        $reseller = self::resellerForCustomer($customer);
        if ($reseller === null) {
            return PortalPaymentGateways::methods($bkashChannel);
        }

        $state = ResellerIntegrationSettings::paymentFormState($reseller);
        $methods = [];

        if ($state['bkash_enabled'] && filled($state['bkash_personal_number'])) {
            $methods[] = array_merge(
                self::personalMethod(PaymentGateway::BKASH, 'bKash', 'Send money to partner number', 'bkash'),
                ['checkout' => PaymentGateway::BKASH_PERSONAL],
            );
        }

        if ($state['nagad_enabled'] && filled($state['nagad_personal_number'])) {
            $methods[] = self::personalMethod(PaymentGateway::NAGAD, 'Nagad', 'Send money to partner number', 'nagad');
        }

        return $methods;
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function gatewayFlags(Customer $customer, ?string $bkashChannel = BkashSettings::CHANNEL_PORTAL): array
    {
        if (! self::hasOwnPayment($customer)) {
            return PortalPaymentGateways::flags($bkashChannel);
        }

        return PublicPaymentMethod::legacyFlags(self::paymentMethods($customer, $bkashChannel));
    }

    /**
     * @return list<string>
     */
    public static function allowedCheckoutGateways(Customer $customer): array
    {
        if (! self::hasOwnPayment($customer)) {
            return PaymentGateway::customerCheckoutGateways();
        }

        $gateways = [];
        foreach (self::paymentMethods($customer) as $method) {
            $gateways[] = (string) ($method['checkout'] ?? $method['gateway']);
        }

        return array_values(array_unique($gateways));
    }

    private static function normalizePaymentRuntime(Reseller $reseller): void
    {
        $state = ResellerIntegrationSettings::paymentFormState($reseller);

        if ($state['nagad_enabled'] && filled($state['nagad_personal_number'])) {
            config([
                'nagad.enabled' => true,
                'nagad.gateway_type' => 'personal',
                'nagad.personal_number' => $state['nagad_personal_number'],
            ]);
        }

        if ($state['bkash_enabled'] && filled($state['bkash_personal_number'])) {
            config([
                'bkash.personal_enabled' => true,
                'bkash.personal_number' => $state['bkash_personal_number'],
                'bkash.personal_name' => $state['bkash_personal_name'] ?: ($reseller->brand_name ?: $reseller->name),
            ]);
        }
    }

    /**
     * @return array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string}
     */
    private static function personalMethod(string $gateway, string $label, string $hint, string $tone): array
    {
        return [
            'gateway' => $gateway,
            'mode' => PublicPaymentMethod::MODE_PERSONAL,
            'label' => $label,
            'badge' => 'Personal',
            'hint' => $hint,
            'tone' => $tone,
        ];
    }
}
