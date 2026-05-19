<?php

namespace App\Support;

final class ResellerType
{
    public const RESELLER = 'reseller';

    public const FRANCHISE = 'franchise';

    public const SUB_RESELLER = 'sub_reseller';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::RESELLER => 'Reseller',
            self::FRANCHISE => 'Franchise',
            self::SUB_RESELLER => 'Sub-reseller',
        ];
    }
}
