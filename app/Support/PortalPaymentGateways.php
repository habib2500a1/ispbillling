<?php

namespace App\Support;

final class PortalPaymentGateways
{
    /**
     * @return list<array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string}>
     */
    public static function methods(?string $bkashChannel = BkashSettings::CHANNEL_PORTAL): array
    {
        return PublicPaymentMethod::collect($bkashChannel);
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function flags(?string $bkashChannel = BkashSettings::CHANNEL_PORTAL): array
    {
        return PublicPaymentMethod::legacyFlags(self::methods($bkashChannel));
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function forPublicBillPay(): array
    {
        return self::flags(BkashSettings::CHANNEL_PUBLIC_PAY);
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function forCustomerPortal(): array
    {
        return self::flags(BkashSettings::CHANNEL_PORTAL);
    }

    /**
     * @return list<array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string}>
     */
    public static function methodsForPublicBillPay(): array
    {
        return self::methods(BkashSettings::CHANNEL_PUBLIC_PAY);
    }

    /**
     * @return list<array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string}>
     */
    public static function methodsForCustomerPortal(): array
    {
        return self::methods(BkashSettings::CHANNEL_PORTAL);
    }
}
