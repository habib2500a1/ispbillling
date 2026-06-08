<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Support\Rbac\StaffCapability;
use App\Models\Invoice;
use App\Models\Payment;
use App\Filament\Widgets\AgedReceivablesWidget;
use App\Filament\Widgets\RevenueTrendChartWidget;
use App\Services\Billing\AdminBillingNoticesService;
use App\Services\Billing\BillingOpsMetricsService;
use App\Services\Dashboard\BillingDashboardMetricsService;
use App\Services\Dashboard\DashboardMetricsService;
use Filament\Pages\Page;

class BillingOverview extends Page
{
    use CachesHubStats;
    use HidesHubNavigation;

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    protected static string $view = 'filament.pages.billing-overview';

    protected static ?string $navigationLabel = 'Billing center';

    protected static ?string $title = '';

    public function getTitle(): string
    {
        return '';
    }

    protected static ?string $navigationGroup = 'Billing';

    protected static ?int $navigationSort = 0;

    public static function canAccess(): bool
    {
        return StaffCapability::for(auth()->user())->canBilling();
    }

    /**
     * @return array<string, string|bool>
     */
    public function getExtraBodyAttributes(): array
    {
        return [
            'class' => 'isp-billing-module',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getRevenueAnalytics(): array
    {
        $payload = app(BillingDashboardMetricsService::class)->payload();
        $kpis = collect($payload['kpis'] ?? [])->keyBy('key');
        $growth = $payload['growth'] ?? [];
        $monthlyBill = (float) ($kpis->get('monthly_bill')['value'] ?? 0);
        $collected = (float) ($kpis->get('collected')['value'] ?? 0);
        $rate = $monthlyBill > 0 ? min(100, round(($collected / $monthlyBill) * 100, 1)) : 0;

        $collectedToday = (float) Payment::query()
            ->where('status', 'completed')
            ->whereDate('paid_at', now()->toDateString())
            ->sum('amount');

        return [
            'growth' => $growth,
            'collection_rate' => $rate,
            'collected_today' => $collectedToday,
            'monthly_bill' => $monthlyBill,
            'collected_month' => $collected,
            'total_due' => (float) ($kpis->get('total_due')['value'] ?? 0),
            'income' => (float) ($kpis->get('income')['value'] ?? 0),
        ];
    }

    /**
     * @return array<string, int|float|string>
     */
    public function getStats(): array
    {
        return $this->cachedHubStats(function (): array {
        $openBase = Invoice::query()->whereNotIn('status', ['paid', 'void', 'cancelled', 'draft']);

        $overdue = (clone $openBase)
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereRaw('(total - amount_paid) > 0')
            ->count();

        $outstanding = (clone $openBase)
            ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')
            ->value('due');

        $collectedMonth = (float) Payment::query()
            ->where('status', 'completed')
            ->whereNotNull('paid_at')
            ->whereMonth('paid_at', now()->month)
            ->whereYear('paid_at', now()->year)
            ->sum('amount');

        $ops = app(BillingOpsMetricsService::class)->snapshot();

        return [
            'open' => (clone $openBase)->count(),
            'overdue' => $overdue,
            'draft' => Invoice::query()->where('status', 'draft')->count(),
            'collected_month' => $collectedMonth,
            'outstanding' => max(0.0, (float) $outstanding),
            'ops' => $ops,
        ];
        });
    }

    /**
     * @return list<array{label: string, value: string, hint: string, url: string, tone: string, icon: string}>
     */
    public function getKpiCards(): array
    {
        $s = $this->getStats();

        return [
            [
                'label' => 'Open bills',
                'value' => number_format($s['open']),
                'hint' => 'Unpaid invoices',
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('index'),
                'tone' => 'violet',
                'icon' => 'heroicon-o-queue-list',
            ],
            [
                'label' => 'Overdue',
                'value' => number_format($s['overdue']),
                'hint' => 'Past due date',
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('due'),
                'tone' => 'rose',
                'icon' => 'heroicon-o-exclamation-triangle',
            ],
            [
                'label' => 'Drafts',
                'value' => number_format($s['draft']),
                'hint' => 'Not sent yet',
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('index').'?tableFilters[status][value]=draft',
                'tone' => 'slate',
                'icon' => 'heroicon-o-document',
            ],
            [
                'label' => 'Collected',
                'value' => number_format((float) $s['collected_month'], 0).' BDT',
                'hint' => 'This month',
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('paid'),
                'tone' => 'emerald',
                'icon' => 'heroicon-o-banknotes',
            ],
            [
                'label' => 'Outstanding',
                'value' => number_format((float) $s['outstanding'], 0).' BDT',
                'hint' => 'Total due',
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('due'),
                'tone' => 'amber',
                'icon' => 'heroicon-o-clock',
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: string, meta: string, tone: string, alert?: bool}>
     */
    public function getOpsCards(): array
    {
        $ops = $this->getStats()['ops'] ?? [];

        return [
            [
                'label' => 'Due tomorrow',
                'value' => (string) ($ops['due_tomorrow'] ?? 0),
                'meta' => 'invoices',
                'tone' => 'violet',
            ],
            [
                'label' => 'Over credit limit',
                'value' => (string) ($ops['over_credit_limit'] ?? 0),
                'meta' => 'subscribers',
                'tone' => 'rose',
                'alert' => ($ops['over_credit_limit'] ?? 0) > 0,
            ],
            [
                'label' => 'Prepaid expiring',
                'value' => (string) ($ops['prepaid_expiring_7d'] ?? 0),
                'meta' => 'next 7 days',
                'tone' => 'amber',
            ],
            [
                'label' => 'AR 31–60 days',
                'value' => number_format((float) ($ops['aging']['31_60']['amount'] ?? 0), 0).' BDT',
                'meta' => ($ops['aging']['31_60']['count'] ?? 0).' invoices',
                'tone' => 'indigo',
            ],
            [
                'label' => 'AR 60+ days',
                'value' => number_format((float) ($ops['aging']['60_plus']['amount'] ?? 0), 0).' BDT',
                'meta' => ($ops['aging']['60_plus']['count'] ?? 0).' invoices',
                'tone' => 'rose',
                'alert' => ((float) ($ops['aging']['60_plus']['amount'] ?? 0)) > 0,
            ],
        ];
    }

    public function getNoticeCount(): int
    {
        return app(AdminBillingNoticesService::class)->actionableCount();
    }

    /**
     * @return list<array{label: string, value: string, hint?: string}>
     */
    public function getAnalyticsStats(): array
    {
        $m = app(DashboardMetricsService::class)->billingSnapshot();

        return [
            ['label' => 'Collected (MTD)', 'value' => number_format((float) ($m['collected'] ?? 0), 0).' BDT', 'hint' => 'This month'],
            ['label' => 'Today collection', 'value' => number_format((float) ($m['collected_today'] ?? 0), 0).' BDT', 'hint' => 'Completed payments'],
            ['label' => 'Outstanding', 'value' => number_format((float) ($m['outstanding'] ?? 0), 0).' BDT', 'hint' => 'Total due'],
            ['label' => 'Due customers', 'value' => (string) ($m['due_customers'] ?? 0), 'hint' => 'With open balance'],
            ['label' => 'Open invoices', 'value' => (string) ($m['open_invoices'] ?? 0), 'hint' => 'Draft / open / partial'],
            ['label' => 'Unpaid subs', 'value' => (string) ($m['unpaid'] ?? 0), 'hint' => 'Active with due'],
        ];
    }

    /**
     * @return list<class-string>
     */
    public function getAnalyticsWidgets(): array
    {
        return [
            RevenueTrendChartWidget::class,
            AgedReceivablesWidget::class,
        ];
    }

    /**
     * @return list<array{severity: string, title: string, message: string, url: string, section: string}>
     */
    public function getActionInbox(): array
    {
        $payload = app(AdminBillingNoticesService::class)->payload();
        $items = [];

        foreach ($payload['sections'] ?? [] as $section) {
            foreach ($section['items'] ?? [] as $item) {
                $items[] = [
                    'severity' => (string) ($item['severity'] ?? 'warning'),
                    'title' => (string) ($item['title'] ?? ''),
                    'message' => (string) ($item['message'] ?? ''),
                    'url' => (string) ($item['url'] ?? '#'),
                    'section' => (string) ($section['title'] ?? ''),
                ];
            }
        }

        return array_slice($items, 0, 12);
    }

    public function getDefaultPinsJson(): string
    {
        return json_encode([
            ['label' => 'Collect', 'url' => BillCollectionDesk::getUrl()],
            ['label' => 'Due bills', 'url' => \App\Filament\Resources\InvoiceResource::getUrl('due')],
            ['label' => 'New invoice', 'url' => \App\Filament\Resources\InvoiceResource::getUrl('create')],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string, featured?: bool, external?: bool}>
     */
    public function getToolCards(): array
    {
        $cards = [];

        if (BillingNoticesPage::canAccess() && $this->getNoticeCount() > 0) {
            $cards[] = [
                'title' => 'Billing notices',
                'desc' => 'MFS verify pending · overdue · due in 3 days',
                'url' => BillingNoticesPage::getUrl(),
                'icon' => 'heroicon-o-bell-alert',
                'tone' => 'rose',
                'featured' => true,
            ];
        }

        return [
            ...$cards,
            [
                'title' => 'Bill collection desk',
                'desc' => 'Cashier — search ID, phone, name & collect payment.',
                'url' => BillCollectionDesk::getUrl(),
                'icon' => 'heroicon-o-currency-bangladeshi',
                'tone' => 'emerald',
                'featured' => true,
            ],
            [
                'title' => 'All bills',
                'desc' => 'Invoices · generate · print · late fee · coupon.',
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('index'),
                'icon' => 'heroicon-o-queue-list',
                'tone' => 'violet',
            ],
            [
                'title' => 'New invoice',
                'desc' => 'Manual one-off charge or adjustment.',
                'url' => \App\Filament\Resources\InvoiceResource::getUrl('create'),
                'icon' => 'heroicon-o-document-plus',
                'tone' => 'indigo',
            ],
            [
                'title' => 'Staff expenses',
                'desc' => 'Vendor · office · approve reimbursements.',
                'url' => \App\Filament\Resources\StaffExpenseResource::getUrl('index'),
                'icon' => 'heroicon-o-receipt-refund',
                'tone' => 'rose',
            ],
            [
                'title' => 'Coupons',
                'desc' => 'Promo codes on subscriber bills.',
                'url' => \App\Filament\Resources\CouponResource::getUrl('index'),
                'icon' => 'heroicon-o-ticket',
                'tone' => 'amber',
            ],
            [
                'title' => 'Collector mobile',
                'desc' => 'Field collection · GPS · phone UI.',
                'url' => CollectorMobile::getUrl(),
                'icon' => 'heroicon-o-device-phone-mobile',
                'tone' => 'teal',
            ],
            [
                'title' => 'Wallets',
                'desc' => 'Cashbook · bank · collector · reseller balances.',
                'url' => \App\Filament\Pages\AccountsWalletHubPage::getUrl(),
                'icon' => 'heroicon-o-wallet',
                'tone' => 'indigo',
            ],
            [
                'title' => 'Customer /pay page',
                'desc' => 'Public self-pay portal (new tab).',
                'url' => route('bill-payment.index'),
                'icon' => 'heroicon-o-globe-alt',
                'tone' => 'teal',
                'external' => true,
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, chips: list<string>}>
     */
    public function getReportCards(): array
    {
        return [
            [
                'title' => 'Payment & collection report',
                'desc' => 'All gateways · filter bKash · CSV export.',
                'url' => \App\Filament\Pages\PaymentsReport::getUrl(),
                'chips' => ['bKash', 'Nagad', 'Cash'],
            ],
            [
                'title' => "Today's collection",
                'desc' => 'Desk receipts by date, user, and customer.',
                'url' => CollectionDeskReport::getUrl(['preset' => 'today']),
                'chips' => ['Today'],
            ],
            [
                'title' => 'Monthly collection',
                'desc' => 'Month-to-date collection summary.',
                'url' => CollectionDeskReport::getUrl(['preset' => 'month']),
                'chips' => ['MTD'],
            ],
            [
                'title' => 'Bill money trail',
                'desc' => 'Where cash went · print · CSV export.',
                'url' => BillingFundFlowReport::getUrl(),
                'chips' => ['Ledger'],
            ],
            [
                'title' => 'Dunning report',
                'desc' => 'Overdue reminders · grace · suspension pipeline.',
                'url' => DunningReport::getUrl(),
                'chips' => ['AR'],
            ],
            [
                'title' => 'Gateway reconciliation',
                'desc' => 'Match bKash/Nagad vs ledger.',
                'url' => GatewayReconciliationReport::getUrl(),
                'chips' => ['MFS'],
            ],
            [
                'title' => 'Collector visits',
                'desc' => 'Field visits · GPS · collection proof.',
                'url' => CollectorVisitsReport::getUrl(),
                'chips' => ['Field'],
            ],
        ];
    }

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string, featured?: bool, external?: bool}>
     */
    public function getActionCards(): array
    {
        return $this->getToolCards();
    }
}
