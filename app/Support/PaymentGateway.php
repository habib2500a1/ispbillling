<?php

namespace App\Support;

final class PaymentGateway
{
    public const BKASH = 'bkash';

    public const NAGAD = 'nagad';

    public const ROCKET = 'rocket';

    public const SSLCOMMERZ = 'sslcommerz';

    public const PIPRAPAY = 'piprapay';

    public const STRIPE = 'stripe';

    public const PAYPAL = 'paypal';

    public const CASH = 'cash';

    public const BANK = 'bank';

    public const WALLET = 'wallet';

    public const OTHER = 'other';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::CASH => 'Cash',
            self::BANK => 'Bank transfer',
            self::BKASH => 'bKash',
            self::NAGAD => 'Nagad',
            self::ROCKET => 'Rocket',
            self::SSLCOMMERZ => 'SSLCommerz',
            self::PIPRAPAY => 'PipraPay',
            self::STRIPE => 'Stripe',
            self::PAYPAL => 'PayPal',
            self::WALLET => 'Wallet / balance',
            self::OTHER => 'Other',
        ];
    }

    public static function label(string $method): string
    {
        return self::options()[$method] ?? ucfirst($method);
    }

    /** Gateways that support automated webhooks. */
    public static function webhookGateways(): array
    {
        return [
            self::BKASH,
            self::NAGAD,
            self::ROCKET,
            self::SSLCOMMERZ,
            self::PIPRAPAY,
            self::STRIPE,
            self::PAYPAL,
        ];
    }
}
