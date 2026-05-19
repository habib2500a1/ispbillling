<?php

namespace App\Filament\Pages;

use App\Models\BankAccount;
use App\Models\CashbookEntry;
use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use App\Models\Vendor;
use App\Models\VendorPayment;
use App\Services\Accounting\AccountingReportService;
use App\Services\Accounting\CashbookService;
use App\Services\Accounting\ChartOfAccountSeeder;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AccountingHub extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.pages.accounting-hub';

    protected static ?string $navigationLabel = 'Accounting & finance';

    protected static ?string $title = 'Accounting & finance';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 1;

    /**
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $from = now()->startOfMonth();
        $to = now()->endOfMonth();
        $reports = app(AccountingReportService::class);
        $pl = $reports->profitAndLoss($from, $to);
        $snap = $reports->incomeExpenseSnapshot($from, $to);
        $cash = app(CashbookService::class)->runningBalance();

        $income = (float) $pl['income'];
        $expenses = (float) $pl['expenses'];
        $profit = (float) $pl['net_profit'];
        $margin = $income > 0 ? round(($profit / $income) * 100, 1) : 0.0;

        return [
            'period_label' => $from->format('F Y'),
            'accounts' => ChartOfAccount::query()->count(),
            'journals' => JournalEntry::query()->whereMonth('entry_date', now()->month)->count(),
            'banks' => BankAccount::query()->where('is_active', true)->count(),
            'bank_balance' => (float) BankAccount::query()->sum('current_balance'),
            'vendors' => Vendor::query()->where('is_active', true)->count(),
            'employees' => Employee::query()->where('is_active', true)->count(),
            'cash_balance' => $cash,
            'month_income' => $income,
            'month_expenses' => $expenses,
            'month_profit' => $profit,
            'profit_margin' => $margin,
            'collections' => $snap['collections'],
            'cashbook_in' => $snap['cashbook_in'],
            'cashbook_out' => $snap['cashbook_out'],
            'income_pct' => $income + $expenses > 0 ? round(($income / ($income + $expenses)) * 100) : 50,
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string, tone: string}>
     */
    public function getQuickActions(): array
    {
        return [
            [
                'label' => 'Cash in',
                'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('create'),
                'icon' => 'arrow-down-tray',
                'tone' => 'emerald',
            ],
            [
                'label' => 'Cash out',
                'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('create'),
                'icon' => 'arrow-up-tray',
                'tone' => 'rose',
            ],
            [
                'label' => 'Journal',
                'url' => \App\Filament\Resources\JournalEntryResource::getUrl('create'),
                'icon' => 'book-open',
                'tone' => 'violet',
            ],
            [
                'label' => 'Vendor pay',
                'url' => \App\Filament\Resources\VendorPaymentResource::getUrl('create'),
                'icon' => 'banknotes',
                'tone' => 'amber',
            ],
            [
                'label' => 'Reports',
                'url' => FinancialReports::getUrl(),
                'icon' => 'chart-bar',
                'tone' => 'cyan',
            ],
            [
                'label' => 'GL auto-post',
                'url' => ManageAccountingIntegration::getUrl(),
                'icon' => 'cog-6-tooth',
                'tone' => 'slate',
            ],
            [
                'label' => 'Cashbook',
                'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('index'),
                'icon' => 'wallet',
                'tone' => 'indigo',
            ],
        ];
    }

    /**
     * @return list<array{title: string, subtitle: string, tone: string, icon: string, items: list<array{title: string, description: string, url: string, badge: ?string, icon: string}>}>
     */
    public function getModuleGroups(): array
    {
        $stats = $this->getStats();

        return [
            [
                'title' => 'Daily cash',
                'subtitle' => 'Receipts, payments & running balance',
                'tone' => 'emerald',
                'icon' => 'wallet',
                'items' => [
                    [
                        'title' => 'Cashbook',
                        'description' => number_format($stats['cashbook_in'], 0).' in · '.number_format($stats['cashbook_out'], 0).' out this month',
                        'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('index'),
                        'badge' => 'Live',
                        'icon' => 'book-open',
                    ],
                    [
                        'title' => 'Record cash in',
                        'description' => 'Walk-in collection, petty cash receipt',
                        'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('create'),
                        'badge' => null,
                        'icon' => 'arrow-down-tray',
                    ],
                    [
                        'title' => 'Record cash out',
                        'description' => 'Petty expense, office disbursement',
                        'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('create'),
                        'badge' => null,
                        'icon' => 'arrow-up-tray',
                    ],
                ],
            ],
            [
                'title' => 'Ledger & GL',
                'subtitle' => 'Double-entry journals & chart',
                'tone' => 'violet',
                'icon' => 'book-open',
                'items' => [
                    [
                        'title' => 'General ledger',
                        'description' => 'Posted journals & debit/credit lines',
                        'url' => \App\Filament\Resources\JournalEntryResource::getUrl('index'),
                        'badge' => (string) $stats['journals'].' MTD',
                        'icon' => 'document-text',
                    ],
                    [
                        'title' => 'New journal entry',
                        'description' => 'Manual GL posting',
                        'url' => \App\Filament\Resources\JournalEntryResource::getUrl('create'),
                        'badge' => null,
                        'icon' => 'plus-circle',
                    ],
                    [
                        'title' => 'Chart of accounts',
                        'description' => $stats['accounts'].' GL accounts · assets, income, expenses',
                        'url' => \App\Filament\Resources\ChartOfAccountResource::getUrl('index'),
                        'badge' => null,
                        'icon' => 'table-cells',
                    ],
                ],
            ],
            [
                'title' => 'Banking',
                'subtitle' => 'Accounts & balances',
                'tone' => 'cyan',
                'icon' => 'building-library',
                'items' => [
                    [
                        'title' => 'Bank accounts',
                        'description' => number_format($stats['bank_balance'], 0).' BDT across '.$stats['banks'].' accounts',
                        'url' => \App\Filament\Resources\BankAccountResource::getUrl('index'),
                        'badge' => null,
                        'icon' => 'building-library',
                    ],
                ],
            ],
            [
                'title' => 'Payables',
                'subtitle' => 'Vendors & supplier payments',
                'tone' => 'amber',
                'icon' => 'truck',
                'items' => [
                    [
                        'title' => 'Vendors',
                        'description' => $stats['vendors'].' active suppliers',
                        'url' => \App\Filament\Resources\VendorResource::getUrl('index'),
                        'badge' => null,
                        'icon' => 'building-storefront',
                    ],
                    [
                        'title' => 'Vendor payments',
                        'description' => 'Bills, VAT & ledger posting',
                        'url' => \App\Filament\Resources\VendorPaymentResource::getUrl('index'),
                        'badge' => null,
                        'icon' => 'banknotes',
                    ],
                    [
                        'title' => 'New vendor payment',
                        'description' => 'Pay supplier with VAT split',
                        'url' => \App\Filament\Resources\VendorPaymentResource::getUrl('create'),
                        'badge' => null,
                        'icon' => 'plus-circle',
                    ],
                ],
            ],
            [
                'title' => 'Payroll',
                'subtitle' => 'Staff salaries',
                'tone' => 'fuchsia',
                'icon' => 'user-group',
                'items' => [
                    [
                        'title' => 'Employees',
                        'description' => $stats['employees'].' active staff',
                        'url' => \App\Filament\Resources\EmployeeResource::getUrl('index'),
                        'badge' => null,
                        'icon' => 'users',
                    ],
                    [
                        'title' => 'Payroll runs',
                        'description' => 'Monthly salary processing',
                        'url' => \App\Filament\Resources\PayrollRunResource::getUrl('index'),
                        'badge' => null,
                        'icon' => 'calendar-days',
                    ],
                ],
            ],
            [
                'title' => 'Reports',
                'subtitle' => 'P&L, VAT & period filters',
                'tone' => 'indigo',
                'icon' => 'chart-bar',
                'items' => [
                    [
                        'title' => 'P&L & VAT reports',
                        'description' => 'Profit/loss, VAT summary, custom dates',
                        'url' => FinancialReports::getUrl(),
                        'badge' => 'Open',
                        'icon' => 'chart-pie',
                    ],
                ],
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('seedChart')
                ->label('Seed chart of accounts')
                ->icon('heroicon-o-sparkles')
                ->action(function (): void {
                    $n = app(ChartOfAccountSeeder::class)->seedForTenant();
                    Notification::make()
                        ->title('Chart of accounts ready')
                        ->body($n > 0 ? "Created {$n} default accounts." : 'Default accounts already exist.')
                        ->success()
                        ->send();
                }),
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && ($user->hasRole('super-admin') || $user->hasRole('isp-admin'));
    }
}
