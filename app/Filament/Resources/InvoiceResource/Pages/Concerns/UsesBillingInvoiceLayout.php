<?php

namespace App\Filament\Resources\InvoiceResource\Pages\Concerns;

use App\Filament\Pages\BillingNoticesPage;
use App\Filament\Pages\BillingOverview;
use App\Filament\Pages\BillCollectionDesk;
use App\Filament\Resources\InvoiceResource;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Billing\BillingInvoiceCounts;
use Illuminate\Support\Facades\Cache;

/**
 * Premium billing invoice list chrome (UI only — preserves Filament table/actions).
 */
trait UsesBillingInvoiceLayout
{
    /** @var array<string, int>|null */
    protected ?array $billingCounts = null;

    public function bootUsesBillingInvoiceLayout(): void
    {
        //
    }

    abstract protected function getBillingListVariant(): string;

    public function getBillingPageVariant(): string
    {
        return $this->getBillingListVariant();
    }

    /**
     * @return array<string, int>
     */
    protected function getBillingCounts(): array
    {
        if ($this->billingCounts !== null) {
            return $this->billingCounts;
        }

        try {
            $this->billingCounts = app(BillingInvoiceCounts::class)->all();
        } catch (\Throwable) {
            $this->billingCounts = ['all' => 0, 'due' => 0, 'paid' => 0, 'today_collection' => 0];
        }

        return $this->billingCounts;
    }

    /**
     * @return list<array{key: string, label: string, count: int, url: string, active: bool}>
     */
    public function getBillingNavTabs(): array
    {
        $counts = $this->getBillingCounts();
        $variant = $this->getBillingPageVariant();

        return [
            [
                'key' => 'all',
                'label' => 'All bills',
                'count' => $counts['all'] ?? 0,
                'url' => InvoiceResource::getUrl('index'),
                'active' => $variant === 'all',
            ],
            [
                'key' => 'due',
                'label' => 'Due bills',
                'count' => $counts['due'] ?? 0,
                'url' => InvoiceResource::getUrl('due'),
                'active' => $variant === 'due',
            ],
            [
                'key' => 'paid',
                'label' => 'Paid bills',
                'count' => $counts['paid'] ?? 0,
                'url' => InvoiceResource::getUrl('paid'),
                'active' => $variant === 'paid',
            ],
        ];
    }

    /**
     * @return list<array{label: string, value: string, hint?: string, tone: string, icon: string, url?: string}>
     */
    public function getBillingStatCards(): array
    {
        $tenantId = \App\Support\TenantResolver::requiredTenantId();
        $cacheKey = "billing_list_stats:{$tenantId}:".now()->format('Y-m-d-H').":".$this->getBillingPageVariant();

        return Cache::remember($cacheKey, 60, function (): array {
            $openBase = Invoice::query()->whereNotIn('status', ['paid', 'void', 'cancelled', 'draft']);
            $outstanding = (float) (clone $openBase)
                ->selectRaw('COALESCE(SUM(total - amount_paid), 0) as due')
                ->value('due');
            $overdue = (clone $openBase)
                ->whereDate('due_date', '<', now()->toDateString())
                ->whereRaw('(total - amount_paid) > 0')
                ->count();
            $collectedToday = (float) Payment::query()
                ->where('status', 'completed')
                ->whereDate('paid_at', now()->toDateString())
                ->sum('amount');
            $counts = $this->getBillingCounts();

            return [
                [
                    'label' => 'All bills',
                    'value' => number_format($counts['all'] ?? 0),
                    'tone' => 'violet',
                    'icon' => 'heroicon-o-queue-list',
                    'url' => InvoiceResource::getUrl('index'),
                ],
                [
                    'label' => 'Due bills',
                    'value' => number_format($counts['due'] ?? 0),
                    'tone' => 'amber',
                    'icon' => 'heroicon-o-exclamation-triangle',
                    'url' => InvoiceResource::getUrl('due'),
                ],
                [
                    'label' => 'Outstanding',
                    'value' => number_format(max(0, $outstanding), 0).' BDT',
                    'tone' => 'rose',
                    'icon' => 'heroicon-o-banknotes',
                ],
                [
                    'label' => "Today's collection",
                    'value' => number_format($collectedToday, 0).' BDT',
                    'hint' => (string) ($counts['today_collection'] ?? 0).' payments',
                    'tone' => 'emerald',
                    'icon' => 'heroicon-o-currency-bangladeshi',
                    'url' => BillCollectionDesk::getUrl(),
                ],
            ];
        });
    }

    public function getBillingHeroTitle(): string
    {
        return match ($this->getBillingPageVariant()) {
            'due' => 'Due bills',
            'paid' => 'Paid bills',
            default => 'All bills',
        };
    }

    public function getBillingHeroSubtitle(): string
    {
        return match ($this->getBillingPageVariant()) {
            'due' => 'Open, partial, and draft invoices — collect payments and send reminders.',
            'paid' => 'Completed invoices — receipts, exports, and billing history.',
            default => 'Search, filter, and manage every invoice — auto billing, coupons, late fees, PDF.',
        };
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function getBillingFilterChips(): array
    {
        $chips = [];
        $status = data_get($this->tableFilters, 'status.value');
        $overdue = (bool) data_get($this->tableFilters, 'overdue.isActive', false);
        $customerId = data_get($this->tableFilters, 'customer_id.value');

        if (filled($status)) {
            $chips[] = ['key' => 'status', 'label' => 'Status: '.ucfirst((string) $status)];
        }

        if ($overdue) {
            $chips[] = ['key' => 'overdue', 'label' => 'Overdue only'];
        }

        if (filled($customerId)) {
            $chips[] = ['key' => 'customer_id', 'label' => 'Subscriber filtered'];
        }

        if (filled($this->tableSearch)) {
            $chips[] = ['key' => 'search', 'label' => 'Search: '.$this->tableSearch];
        }

        return $chips;
    }

    public function getBillingActiveFilterCount(): int
    {
        return count($this->getBillingFilterChips());
    }

    /**
     * @return list<array{key: string, label: string, url: string}>
     */
    public function getBillingSavedFilters(): array
    {
        $index = \App\Filament\Resources\InvoiceResource::getUrl('index');
        $due = \App\Filament\Resources\InvoiceResource::getUrl('due');
        $paid = \App\Filament\Resources\InvoiceResource::getUrl('paid');

        return [
            ['key' => 'due', 'label' => 'Due bills', 'url' => $due],
            ['key' => 'paid', 'label' => 'Paid bills', 'url' => $paid],
            ['key' => 'draft', 'label' => 'Drafts', 'url' => $index.'?tableFilters[status][value]=draft'],
            ['key' => 'open', 'label' => 'Open', 'url' => $index.'?tableFilters[status][value]=open'],
            ['key' => 'overdue', 'label' => 'Overdue', 'url' => $due.'?tableFilters[overdue][isActive]=1'],
        ];
    }

    public function getBillingResultSummary(): string
    {
        $count = $this->getTableRecords()->total();
        $variant = $this->getBillingHeroTitle();

        return number_format($count).' '.$variant;
    }

    public function resetBillingToolbar(): void
    {
        $this->tableSearch = '';
        $this->tableFilters = [];
        $this->resetPage();
    }

    public function getBillingFilterChipUrl(string $key): string
    {
        $params = [];

        if ($key !== 'search' && filled($this->tableSearch)) {
            $params['tableSearch'] = $this->tableSearch;
        }

        $filters = $this->tableFilters ?? [];

        if ($key !== 'status') {
            unset($filters['status']);
        }

        if ($key !== 'overdue') {
            unset($filters['overdue']);
        }

        if ($key !== 'customer_id') {
            unset($filters['customer_id']);
        }

        if ($filters !== []) {
            $params['tableFilters'] = $filters;
        }

        $route = match ($this->getBillingPageVariant()) {
            'due' => 'due',
            'paid' => 'paid',
            default => 'index',
        };

        return InvoiceResource::getUrl($route, $params);
    }

    /**
     * @return list<array{url: string, label: string, icon: string, active?: bool}>
     */
    public function getBillingDockLinks(): array
    {
        $variant = $this->getBillingPageVariant();

        return [
            ['url' => BillingOverview::getUrl(), 'label' => 'Center', 'icon' => 'heroicon-o-squares-2x2'],
            ['url' => InvoiceResource::getUrl('index'), 'label' => 'Bills', 'icon' => 'heroicon-o-queue-list', 'active' => in_array($variant, ['all', 'due', 'paid'], true)],
            ['url' => BillCollectionDesk::getUrl(), 'label' => 'Collect', 'icon' => 'heroicon-o-currency-bangladeshi'],
            ['url' => InvoiceResource::getUrl('create'), 'label' => 'New', 'icon' => 'heroicon-o-document-plus'],
            ['url' => BillingNoticesPage::getUrl(), 'label' => 'Alerts', 'icon' => 'heroicon-o-bell-alert'],
        ];
    }
}
