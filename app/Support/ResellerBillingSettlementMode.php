<?php

namespace App\Support;

final class ResellerBillingSettlementMode
{
    public const POSTPAID_DUE = 'postpaid_due';

    public const WALLET_PREPAID = 'wallet_prepaid';

    public const HYBRID = 'hybrid';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::POSTPAID_DUE => 'Postpaid due account (wholesale accrues monthly)',
            self::WALLET_PREPAID => 'Prepaid wallet (debit on each bill)',
            self::HYBRID => 'Hybrid (wallet first, remainder to due)',
        ];
    }
}
