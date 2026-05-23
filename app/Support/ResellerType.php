<?php

namespace App\Support;

final class ResellerType
{
    public const MASTER_RESELLER = 'master_reseller';

    public const RESELLER = 'reseller';

    public const FRANCHISE = 'franchise';

    public const SUB_RESELLER = 'sub_reseller';

    public const AREA_DISTRIBUTOR = 'area_distributor';

    public const LOCAL_PARTNER = 'local_partner';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::MASTER_RESELLER => 'Master reseller',
            self::RESELLER => 'Reseller',
            self::FRANCHISE => 'Franchise',
            self::SUB_RESELLER => 'Sub-reseller',
            self::AREA_DISTRIBUTOR => 'Area distributor',
            self::LOCAL_PARTNER => 'Local ISP partner',
        ];
    }

    /** Hierarchy depth for display (lower = higher in tree). */
    public static function tier(string $type): int
    {
        return match ($type) {
            self::MASTER_RESELLER => 0,
            self::FRANCHISE, self::AREA_DISTRIBUTOR => 1,
            self::RESELLER => 2,
            self::SUB_RESELLER, self::LOCAL_PARTNER => 3,
            default => 2,
        };
    }
}
