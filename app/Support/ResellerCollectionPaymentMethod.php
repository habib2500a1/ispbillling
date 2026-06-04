<?php

namespace App\Support;

final class ResellerCollectionPaymentMethod
{
    /** @return array<string, string> */
    public static function options(): array
    {
        return [
            PaymentGateway::CASH => 'Cash',
            PaymentGateway::BKASH => 'bKash',
            PaymentGateway::NAGAD => 'Nagad',
            PaymentGateway::ROCKET => 'Rocket',
            PaymentGateway::BANK => 'Bank transfer',
            PaymentGateway::OTHER => 'Other',
        ];
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_keys(self::options());
    }
}
