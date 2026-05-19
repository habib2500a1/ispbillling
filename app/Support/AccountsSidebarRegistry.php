<?php

namespace App\Support;

use App\Filament\Pages\AccountingHub;
use App\Filament\Pages\AccountsExpensesPage;
use App\Filament\Pages\AccountsHub;
use App\Filament\Pages\AccountsIncomePage;
use App\Filament\Pages\AccountsIncomeVsExpensePage;
use App\Filament\Pages\AccountsMySalaryPage;
use App\Filament\Pages\AccountsWalletHubPage;
use App\Filament\Pages\CollectorCashHub;
use App\Filament\Pages\FinancialReports;
use App\Filament\Pages\ManagePaymentSettings;
use App\Filament\Pages\PaymentsReport;
use App\Filament\Resources\BandwidthClientResource;
use App\Filament\Resources\BankAccountResource;
use App\Filament\Resources\CashbookEntryResource;
use App\Filament\Resources\ChartOfAccountResource;
use App\Filament\Resources\JournalEntryResource;
use App\Filament\Resources\VendorPaymentResource;
use App\Filament\Resources\VendorResource;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;

final class AccountsSidebarRegistry
{
    /**
     * @return list<array{key: string, label: string, icon: string, sort: int, url: string, active_routes: list<string>}>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'wallets',
                'label' => 'Wallets',
                'icon' => 'heroicon-o-wallet',
                'sort' => 1,
                'url' => AccountsWalletHubPage::getUrl(),
                'active_routes' => ['filament.admin.pages.accounts-wallet-hub'],
            ],
            [
                'key' => 'dashboard',
                'label' => 'Dashboard',
                'icon' => 'heroicon-o-squares-2x2',
                'sort' => 2,
                'url' => AccountsHub::getUrl(),
                'active_routes' => ['filament.admin.pages.accounts-hub'],
            ],
            [
                'key' => 'balance_transfer',
                'label' => 'Balance transfer',
                'icon' => 'heroicon-o-arrows-right-left',
                'sort' => 3,
                'url' => CollectorCashHub::getUrl(),
                'active_routes' => ['filament.admin.pages.collector-settlement'],
            ],
            [
                'key' => 'bkash',
                'label' => 'bKash payment',
                'icon' => 'heroicon-o-device-phone-mobile',
                'sort' => 4,
                'url' => ManagePaymentSettings::getUrl(),
                'active_routes' => ['filament.admin.pages.payment-gateway-settings'],
            ],
            [
                'key' => 'income_vs_expense',
                'label' => 'Income vs expense',
                'icon' => 'heroicon-o-scale',
                'sort' => 5,
                'url' => AccountsIncomeVsExpensePage::getUrl(),
                'active_routes' => ['filament.admin.pages.accounts-income-vs-expense'],
            ],
            [
                'key' => 'expenses',
                'label' => 'Expenses',
                'icon' => 'heroicon-o-arrow-trending-down',
                'sort' => 7,
                'url' => AccountsExpensesPage::getUrl(),
                'active_routes' => ['filament.admin.pages.accounts-expenses'],
            ],
            [
                'key' => 'income',
                'label' => 'Income',
                'icon' => 'heroicon-o-arrow-trending-up',
                'sort' => 8,
                'url' => AccountsIncomePage::getUrl(),
                'active_routes' => ['filament.admin.pages.accounts-income'],
            ],
            [
                'key' => 'add_expense',
                'label' => 'Add expense',
                'icon' => 'heroicon-o-plus-circle',
                'sort' => 9,
                'url' => VendorPaymentResource::getUrl('create'),
                'active_routes' => ['filament.admin.resources.vendor-payments.create'],
            ],
            [
                'key' => 'my_salary',
                'label' => 'My salary',
                'icon' => 'heroicon-o-banknotes',
                'sort' => 10,
                'url' => AccountsMySalaryPage::getUrl(),
                'active_routes' => ['filament.admin.pages.accounts-my-salary'],
            ],
            [
                'key' => 'financial_reports',
                'label' => 'P&L & VAT reports',
                'icon' => 'heroicon-o-chart-pie',
                'sort' => 11,
                'url' => FinancialReports::getUrl(),
                'active_routes' => ['filament.admin.pages.financial-reports'],
            ],
            [
                'key' => 'ledger',
                'label' => 'Full accounting',
                'icon' => 'heroicon-o-calculator',
                'sort' => 20,
                'url' => AccountingHub::getUrl(),
                'active_routes' => ['filament.admin.pages.accounting-hub'],
            ],
            [
                'key' => 'chart',
                'label' => 'Chart of accounts',
                'icon' => 'heroicon-o-list-bullet',
                'sort' => 21,
                'url' => ChartOfAccountResource::getUrl(),
                'active_routes' => ['filament.admin.resources.chart-of-accounts.*'],
            ],
            [
                'key' => 'journal',
                'label' => 'Journal entries',
                'icon' => 'heroicon-o-book-open',
                'sort' => 22,
                'url' => JournalEntryResource::getUrl(),
                'active_routes' => ['filament.admin.resources.journal-entries.*'],
            ],
            [
                'key' => 'banks',
                'label' => 'Bank accounts',
                'icon' => 'heroicon-o-building-library',
                'sort' => 23,
                'url' => BankAccountResource::getUrl(),
                'active_routes' => ['filament.admin.resources.bank-accounts.*'],
            ],
            [
                'key' => 'vendors',
                'label' => 'Vendors',
                'icon' => 'heroicon-o-truck',
                'sort' => 24,
                'url' => VendorResource::getUrl(),
                'active_routes' => ['filament.admin.resources.vendors.*'],
            ],
            [
                'key' => 'cashbook',
                'label' => 'Cashbook',
                'icon' => 'heroicon-o-clipboard-document-list',
                'sort' => 25,
                'url' => CashbookEntryResource::getUrl(),
                'active_routes' => ['filament.admin.resources.cashbook-entries.*'],
            ],
        ];
    }

    /**
     * @return array<NavigationItem>
     */
    public static function navigationItems(): array
    {
        if (Filament::getCurrentPanel() === null) {
            return [];
        }

        $items = [];

        foreach (self::definitions() as $entry) {
            if (! self::canSeeEntry($entry['key'])) {
                continue;
            }

            $items[] = NavigationItem::make($entry['label'])
                ->url($entry['url'])
                ->icon($entry['icon'])
                ->group('Accounts')
                ->sort($entry['sort'])
                ->isActiveWhen(function () use ($entry): bool {
                    foreach ($entry['active_routes'] as $route) {
                        if (request()->routeIs($route)) {
                            return true;
                        }
                    }

                    return false;
                });
        }

        return $items;
    }

    public static function canSeeEntry(string $key): bool
    {
        return match ($key) {
            'dashboard' => AccountsHub::canAccess(),
            'wallets' => AccountsWalletHubPage::canAccess(),
            'balance_transfer' => CollectorCashHub::canAccess(),
            'bkash' => ManagePaymentSettings::canAccess(),
            'income_vs_expense' => AccountsIncomeVsExpensePage::canAccess(),
            'income' => AccountsIncomePage::canAccess(),
            'expenses', 'add_expense' => VendorPaymentResource::canViewAny(),
            'my_salary' => AccountsMySalaryPage::canAccess(),
            'financial_reports' => FinancialReports::canAccess(),
            'ledger' => AccountingHub::canAccess(),
            'chart' => ChartOfAccountResource::canViewAny(),
            'journal' => JournalEntryResource::canViewAny(),
            'banks' => BankAccountResource::canViewAny(),
            'vendors' => VendorResource::canViewAny(),
            'cashbook' => CashbookEntryResource::canViewAny(),
            default => false,
        };
    }
}
