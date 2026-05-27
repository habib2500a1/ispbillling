<?php

namespace App\Support;

final class PaymentType
{
    public const PAYMENT = 'payment';

    public const REFUND = 'refund';

    public const ADJUSTMENT = 'adjustment';

    public const WALLET_DEPOSIT = 'wallet_deposit';

    public const WALLET_APPLY = 'wallet_apply';

    public const PREPAY = 'prepay';

    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            self::PAYMENT => 'Payment (collection)',
            self::REFUND => 'Refund',
            self::ADJUSTMENT => 'Adjustment',
            self::WALLET_DEPOSIT => 'Wallet top-up',
            self::WALLET_APPLY => 'Pay from wallet',
            self::PREPAY => 'Advance months',
        ];
    }

    public static function label(string $type): string
    {
        return self::options()[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
