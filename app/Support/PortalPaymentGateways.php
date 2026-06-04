<?php

namespace App\Support;

use App\Models\Customer;
use App\Services\Reseller\ResellerPaymentContext;

final class PortalPaymentGateways
{
    /**
     * @return list<array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string}>
     */
    public static function methods(?string $bkashChannel = BkashSettings::CHANNEL_PORTAL, ?Customer $customer = null): array
    {
        if ($customer !== null && ResellerPaymentContext::hasOwnPayment($customer)) {
            return ResellerPaymentContext::paymentMethods($customer, $bkashChannel);
        }

        return PublicPaymentMethod::collect($bkashChannel);
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function flags(?string $bkashChannel = BkashSettings::CHANNEL_PORTAL, ?Customer $customer = null): array
    {
        if ($customer !== null && ResellerPaymentContext::hasOwnPayment($customer)) {
            return ResellerPaymentContext::gatewayFlags($customer, $bkashChannel);
        }

        return PublicPaymentMethod::legacyFlags(self::methods($bkashChannel));
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function forPublicBillPay(?Customer $customer = null): array
    {
        return self::flags(BkashSettings::CHANNEL_PUBLIC_PAY, $customer);
    }

    /**
     * @return array{bkash: bool, sslcommerz: bool, nagad: bool, rocket: bool, piprapay: bool, any: bool}
     */
    public static function forCustomerPortal(?Customer $customer = null): array
    {
        return self::flags(BkashSettings::CHANNEL_PORTAL, $customer);
    }

    /**
     * @return list<array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string}>
     */
    public static function methodsForPublicBillPay(?Customer $customer = null): array
    {
        return self::methods(BkashSettings::CHANNEL_PUBLIC_PAY, $customer);
    }

    /**
     * @return list<array{gateway: string, mode: string, label: string, badge: string, hint: string, tone: string}>
     */
    public static function methodsForCustomerPortal(?Customer $customer = null): array
    {
        return self::methods(BkashSettings::CHANNEL_PORTAL, $customer);
    }
}
