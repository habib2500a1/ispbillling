<?php

namespace App\Services\Accounting;

use App\Models\ChartOfAccount;
use App\Support\AccountType;
use App\Support\TenantResolver;

class ChartOfAccountSeeder
{
    /**
     * @return list<array{code: string, name: string, type: string}>
     */
    public static function defaultAccounts(): array
    {
        return [
            ['code' => '1000', 'name' => 'Cash on hand', 'type' => AccountType::ASSET],
            ['code' => '1050', 'name' => 'Cash with collectors (field)', 'type' => AccountType::ASSET],
            ['code' => '1100', 'name' => 'Bank accounts', 'type' => AccountType::ASSET],
            ['code' => '1200', 'name' => 'Accounts receivable', 'type' => AccountType::ASSET],
            ['code' => '2000', 'name' => 'Accounts payable', 'type' => AccountType::LIABILITY],
            ['code' => '2100', 'name' => 'VAT payable', 'type' => AccountType::LIABILITY],
            ['code' => '3000', 'name' => 'Owner equity', 'type' => AccountType::EQUITY],
            ['code' => '4000', 'name' => 'Subscription revenue', 'type' => AccountType::INCOME],
            ['code' => '4100', 'name' => 'Other income', 'type' => AccountType::INCOME],
            ['code' => '5000', 'name' => 'Operating expenses', 'type' => AccountType::EXPENSE],
            ['code' => '5100', 'name' => 'Payroll expense', 'type' => AccountType::EXPENSE],
            ['code' => '5200', 'name' => 'Vendor & supplier expenses', 'type' => AccountType::EXPENSE],
        ];
    }

    public function seedForTenant(?int $tenantId = null): int
    {
        $tenantId = $tenantId ?? TenantResolver::requiredTenantId();
        $created = 0;

        foreach (self::defaultAccounts() as $row) {
            $account = ChartOfAccount::withoutGlobalScopes()->firstOrCreate(
                ['tenant_id' => $tenantId, 'code' => $row['code']],
                [
                    'name' => $row['name'],
                    'type' => $row['type'],
                    'is_active' => true,
                    'is_system' => true,
                ]
            );
            if ($account->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
