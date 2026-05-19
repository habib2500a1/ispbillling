<?php

namespace App\Support;

final class AccountType
{
    public const ASSET = 'asset';

    public const LIABILITY = 'liability';

    public const EQUITY = 'equity';

    public const INCOME = 'income';

    public const EXPENSE = 'expense';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ASSET => 'Asset',
            self::LIABILITY => 'Liability',
            self::EQUITY => 'Equity',
            self::INCOME => 'Income',
            self::EXPENSE => 'Expense',
        ];
    }

    /**
     * Normal balance: debit increases asset/expense, credit increases liability/equity/income.
     *
     * @return list<string>
     */
    public static function debitNormal(): array
    {
        return [self::ASSET, self::EXPENSE];
    }
}
