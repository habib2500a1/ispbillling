<?php

namespace App\Support;

final class PortalPaymentGateways
{
    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function flags(?string $bkashChannel = BkashSettings::CHANNEL_PORTAL): array
    {
        $bkash = $bkashChannel !== null
            ? BkashSettings::isEnabledForChannel($bkashChannel)
            : BkashSettings::isPaymentEnabled();
        $ssl = (bool) config('sslcommerz.enabled');
        $nagad = (bool) config('nagad.enabled');
        $rocket = (bool) config('bill_payment.gateways.rocket', config('rocket.enabled', false));
        $piprapay = (bool) config('bill_payment.gateways.piprapay', \App\Services\Payments\PipraPayCheckoutService::isEnabled());

        return [
            'bkash' => $bkash,
            'sslcommerz' => $ssl,
            'nagad' => $nagad,
            'rocket' => $rocket,
            'piprapay' => $piprapay,
            'any' => $bkash || $ssl || $nagad || $rocket || $piprapay,
        ];
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, any: bool}
     */
    public static function forPublicBillPay(): array
    {
        return self::flags(BkashSettings::CHANNEL_PUBLIC_PAY);
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, any: bool}
     */
    public static function forCustomerPortal(): array
    {
        return self::flags(BkashSettings::CHANNEL_PORTAL);
    }
}
