<?php

namespace App\Filament\Pages;

use App\Services\Accounting\ChartOfAccountSeeder;
use App\Services\Finance\FinanceHubDashboardService;
use App\Support\AccountsSidebarRegistry;
use App\Support\TenantResolver;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class AccountingHub extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationIcon = 'heroicon-o-calculator';

    protected static string $view = 'filament.pages.accounting-hub';

    protected static ?string $navigationLabel = 'Finance operations';

    protected static ?string $title = 'Finance Operations Center';

    protected static ?string $navigationGroup = 'Accounting';

    protected static ?int $navigationSort = 1;

    /** @var array<string, mixed> */
    public array $finance = [];

    public string $searchQuery = '';

    /** @var list<array<string, mixed>> */
    public array $searchResults = [];

    public string $activeTab = 'dashboard';

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $this->refreshFinance();
    }

    public function refreshFinance(): void
    {
        $this->finance = app(FinanceHubDashboardService::class)->snapshot();
    }

    public function updatedSearchQuery(): void
    {
        $this->searchResults = app(FinanceHubDashboardService::class)->search($this->searchQuery);
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    /**
     * @return array<string, mixed>
     */
    public function getViewData(): array
    {
        $finance = $this->finance;
        $kpis = $finance['kpis'] ?? [];
        $accounts = $finance['accounts'] ?? [];
        $tenantId = TenantResolver::currentTenantId() ?? 1;
        $gl = app(FinanceHubDashboardService::class)->glCounts($tenantId);
        $profitPositive = ($kpis['net_profit'] ?? 0) >= 0;

        $kpiCards = [
            ['key' => 'total_revenue', 'label' => 'Total revenue', 'class' => 'isp-fin-kpi--revenue'],
            ['key' => 'today_collection', 'label' => "Today's collection", 'class' => 'isp-fin-kpi--today'],
            ['key' => 'monthly_collection', 'label' => 'Monthly collection', 'class' => 'isp-fin-kpi--month'],
            ['key' => 'due_collection', 'label' => 'Due collection', 'class' => 'isp-fin-kpi--due'],
            ['key' => 'overdue_collection', 'label' => 'Overdue', 'class' => 'isp-fin-kpi--overdue'],
            ['key' => 'total_expenses', 'label' => 'Total expenses', 'class' => 'isp-fin-kpi--expense'],
            ['key' => 'net_profit', 'label' => 'Net profit', 'class' => $profitPositive ? 'isp-fin-kpi--profit' : 'isp-fin-kpi--loss'],
            ['key' => 'cash_flow', 'label' => 'Cash flow', 'class' => 'isp-fin-kpi--flow'],
            ['key' => 'bank_balance', 'label' => 'Bank balance', 'class' => 'isp-fin-kpi--bank'],
            ['key' => 'mobile_banking', 'label' => 'Mobile / field cash', 'class' => 'isp-fin-kpi--mfs'],
        ];

        return [
            'finance' => $finance,
            'kpis' => $kpis,
            'accounts' => $accounts,
            'gl' => $gl,
            'profitPositive' => $profitPositive,
            'kpiCards' => $kpiCards,
            'quickActions' => $this->getQuickActions(),
            'moduleGroups' => $this->getModuleGroups($gl, $accounts),
            'navLinks' => AccountsSidebarRegistry::definitions(),
            'footerLinks' => [
                ['url' => BillingOverview::getUrl(), 'label' => 'Billing', 'icon' => 'heroicon-o-banknotes'],
                ['url' => BillCollectionDesk::getUrl(), 'label' => 'Collect', 'icon' => 'heroicon-o-currency-bangladeshi'],
                ['url' => AccountsHub::getUrl(), 'label' => 'Accounts', 'icon' => 'heroicon-o-squares-2x2'],
                ['url' => FinancialReports::getUrl(), 'label' => 'Reports', 'icon' => 'heroicon-o-chart-bar'],
            ],
        ];
    }

    /**
     * @return list<array{label: string, url: string, icon: string, tone: string}>
     */
    public function getQuickActions(): array
    {
        return [
            ['label' => 'Collect payment', 'url' => BillCollectionDesk::getUrl(), 'icon' => 'currency-bangladeshi', 'tone' => 'emerald'],
            ['label' => 'Cash in', 'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('create'), 'icon' => 'arrow-down-tray', 'tone' => 'teal'],
            ['label' => 'Cash out', 'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('create'), 'icon' => 'arrow-up-tray', 'tone' => 'rose'],
            ['label' => 'Journal', 'url' => \App\Filament\Resources\JournalEntryResource::getUrl('create'), 'icon' => 'book-open', 'tone' => 'violet'],
            ['label' => 'Vendor pay', 'url' => \App\Filament\Resources\VendorPaymentResource::getUrl('create'), 'icon' => 'banknotes', 'tone' => 'amber'],
            ['label' => 'Approve expense', 'url' => \App\Filament\Resources\StaffExpenseResource::getUrl('index'), 'icon' => 'check-badge', 'tone' => 'cyan'],
            ['label' => 'P&L report', 'url' => FinancialReports::getUrl(), 'icon' => 'chart-bar', 'tone' => 'indigo'],
            ['label' => 'GL settings', 'url' => ManageAccountingIntegration::getUrl(), 'icon' => 'cog-6-tooth', 'tone' => 'slate'],
        ];
    }

    /**
     * @param  array<string, int>  $gl
     * @param  array<string, mixed>  $accounts
     * @return list<array<string, mixed>>
     */
    public function getModuleGroups(array $gl, array $accounts): array
    {
        return [
            [
                'title' => 'Income & collection',
                'subtitle' => 'Subscriber payments & billing',
                'tone' => 'emerald',
                'icon' => 'banknotes',
                'items' => [
                    ['title' => 'Bill collection desk', 'description' => 'Cashier — collect & receipt', 'url' => BillCollectionDesk::getUrl(), 'badge' => 'Live', 'icon' => 'currency-bangladeshi'],
                    ['title' => 'Billing center', 'description' => 'Invoices, due, analytics', 'url' => BillingOverview::getUrl(), 'badge' => null, 'icon' => 'receipt-percent'],
                    ['title' => 'Payments ledger', 'description' => 'All completed payments', 'url' => \App\Filament\Resources\PaymentResource::getUrl('index'), 'badge' => null, 'icon' => 'credit-card'],
                ],
            ],
            [
                'title' => 'Daily cash',
                'subtitle' => 'Cashbook & liquidity',
                'tone' => 'cyan',
                'icon' => 'wallet',
                'items' => [
                    ['title' => 'Cashbook', 'description' => number_format($accounts['cashbook_in'] ?? 0, 0).' in · '.number_format($accounts['cashbook_out'] ?? 0, 0).' out', 'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('index'), 'badge' => 'Live', 'icon' => 'book-open'],
                    ['title' => 'Wallet hub', 'description' => 'Cash, bank, collector wallets', 'url' => AccountsWalletHubPage::getUrl(), 'badge' => null, 'icon' => 'building-library'],
                ],
            ],
            [
                'title' => 'Ledger & GL',
                'subtitle' => 'Double-entry accounting',
                'tone' => 'violet',
                'icon' => 'book-open',
                'items' => [
                    ['title' => 'General ledger', 'description' => 'Posted journal entries', 'url' => \App\Filament\Resources\JournalEntryResource::getUrl('index'), 'badge' => (string) ($gl['journals'] ?? 0).' MTD', 'icon' => 'document-text'],
                    ['title' => 'Chart of accounts', 'description' => ($gl['accounts'] ?? 0).' GL accounts', 'url' => \App\Filament\Resources\ChartOfAccountResource::getUrl('index'), 'badge' => null, 'icon' => 'table-cells'],
                    ['title' => 'Income ledger', 'description' => 'Subscriber & other income', 'url' => AccountsIncomePage::getUrl(), 'badge' => null, 'icon' => 'arrow-trending-up'],
                ],
            ],
            [
                'title' => 'Bank & cash',
                'subtitle' => 'Liquidity accounts',
                'tone' => 'blue',
                'icon' => 'building-library',
                'items' => [
                    ['title' => 'Bank accounts', 'description' => ($gl['banks'] ?? 0).' active · '.number_format($accounts['bank_balance'] ?? 0, 0).' BDT', 'url' => \App\Filament\Resources\BankAccountResource::getUrl('index'), 'badge' => null, 'icon' => 'building-library'],
                    ['title' => 'Cash accounts', 'description' => 'Cashbook · '.number_format($accounts['cash_balance'] ?? 0, 0).' BDT on hand', 'url' => \App\Filament\Resources\CashbookEntryResource::getUrl('index'), 'badge' => 'Live', 'icon' => 'wallet'],
                    ['title' => 'Wallet hub', 'description' => 'Collector & mobile wallets', 'url' => AccountsWalletHubPage::getUrl(), 'badge' => null, 'icon' => 'credit-card'],
                ],
            ],
            [
                'title' => 'Expenses & payables',
                'subtitle' => 'Vendors, staff, collectors',
                'tone' => 'amber',
                'icon' => 'truck',
                'items' => [
                    ['title' => 'Staff expenses', 'description' => 'Submit & approve expenses', 'url' => \App\Filament\Resources\StaffExpenseResource::getUrl('index'), 'badge' => null, 'icon' => 'clipboard-document-check'],
                    ['title' => 'Vendor payments', 'description' => ($gl['vendors'] ?? 0).' active vendors', 'url' => \App\Filament\Resources\VendorPaymentResource::getUrl('index'), 'badge' => null, 'icon' => 'banknotes'],
                    ['title' => 'Accounts expenses', 'description' => 'Combined expense view', 'url' => AccountsExpensesPage::getUrl(), 'badge' => null, 'icon' => 'arrow-trending-down'],
                ],
            ],
            [
                'title' => 'Reports',
                'subtitle' => 'P&L, collections, analytics',
                'tone' => 'indigo',
                'icon' => 'chart-bar',
                'items' => [
                    ['title' => 'Financial reports', 'description' => 'P&L · VAT · cashbook', 'url' => FinancialReports::getUrl(), 'badge' => 'Open', 'icon' => 'chart-pie'],
                    ['title' => 'Analytics reports', 'description' => 'Zone · package · churn', 'url' => AnalyticsReports::getUrl(), 'badge' => null, 'icon' => 'presentation-chart-line'],
                    ['title' => 'Fund flow', 'description' => 'Where collections went', 'url' => BillingFundFlowReport::getUrl(), 'badge' => null, 'icon' => 'arrow-path'],
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

        return $user !== null && \App\Support\Rbac\StaffCapability::for($user)->canAccounting();
    }
}
