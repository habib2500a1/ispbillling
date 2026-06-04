<?php

namespace App\Support;

final class ResellerCustomerBillingPolicy
{
    /** Reseller decides suspend; system does not auto-suspend for due. */
    public const RESELLER_CONTROLLED = 'reseller_controlled';

    /** Follow ISP global due rules. */
    public const FOLLOW_ISP_DUE = 'follow_isp_due';

    /** Never auto-suspend for billing due. */
    public const NEVER_AUTO = 'never_auto';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::RESELLER_CONTROLLED => 'Reseller controlled (recommended)',
            self::FOLLOW_ISP_DUE => 'Follow ISP due policy',
            self::NEVER_AUTO => 'Never auto-suspend for due',
        ];
    }
}
