<?php

namespace App\Support;

final class PaymentGateway
{
    public const BKASH = 'bkash';

    public const BKASH_PERSONAL = 'bkash_personal';

    public const BKASH_MERCHANT = 'bkash_merchant';

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

    /**
     * Gateway values accepted from customer checkout forms.
     *
     * @return list<string>
     */
    public static function customerCheckoutGateways(): array
    {
        return [
            self::BKASH,
            self::BKASH_PERSONAL,
            self::BKASH_MERCHANT,
            self::NAGAD,
            self::ROCKET,
            self::SSLCOMMERZ,
            self::PIPRAPAY,
        ];
    }

    /**
     * @return array{gateway: string, mode: ?string}
     */
    public static function resolveCheckoutSelection(string $selection): array
    {
        return match (strtolower(trim($selection))) {
            self::BKASH_PERSONAL => ['gateway' => self::BKASH, 'mode' => 'personal'],
            self::BKASH_MERCHANT => ['gateway' => self::BKASH, 'mode' => 'merchant'],
            default => ['gateway' => strtolower(trim($selection)), 'mode' => null],
        };
    }
}
