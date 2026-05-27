<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\CachesHubStats;
use App\Filament\Pages\Concerns\HidesHubNavigation;
use App\Support\Rbac\StaffCapability;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingOpsMetricsService;
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

    /**
     * @return list<array{title: string, desc: string, url: string, icon: string, tone: string, featured?: bool, external?: bool}>
     */
    public function getActionCards(): array
    {
        return [
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
                'title' => 'Bill money trail',
                'desc' => 'Where cash went · print · CSV export.',
                'url' => BillingFundFlowReport::getUrl(),
                'icon' => 'heroicon-o-arrows-right-left',
                'tone' => 'violet',
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
                'title' => "Today's collection",
                'desc' => 'Desk report for today\'s receipts.',
                'url' => CollectionDeskReport::getUrl(),
                'icon' => 'heroicon-o-calendar-days',
                'tone' => 'sky',
            ],
            [
                'title' => 'Gateway reconciliation',
                'desc' => 'Match bKash/Nagad vs ledger.',
                'url' => GatewayReconciliationReport::getUrl(),
                'icon' => 'heroicon-o-scale',
                'tone' => 'orange',
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
}
