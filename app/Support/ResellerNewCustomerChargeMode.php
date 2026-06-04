<?php

namespace App\Support;

final class ResellerNewCustomerChargeMode
{
    public const PRORATED = 'prorated';

    public const FULL_MONTH = 'full_month';

    public const FIRST_MONTH_FREE = 'first_month_free';

    public const FIRST_MONTH_HALF = 'first_month_half';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::PRORATED => 'Prorated (remaining days in month)',
            self::FULL_MONTH => 'Full month charge',
            self::FIRST_MONTH_FREE => 'First month free (package only)',
            self::FIRST_MONTH_HALF => 'First month 50% (package only)',
        ];
    }
}
