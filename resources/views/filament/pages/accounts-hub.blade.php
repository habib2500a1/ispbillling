@php
    $stats = $this->stats;
@endphp

<x-filament-panels::page class="isp-accounts-page">
    <div class="space-y-5">
        <section class="isp-accounts-hero">
            <div class="isp-accounts-hero__main">
                <p class="isp-accounts-hero__eyebrow">Accounts</p>
                <h2 class="isp-accounts-hero__title">Finance dashboard</h2>
                <p class="isp-accounts-hero__sub">Income, expenses, wallets, collections, and profit for the selected period.</p>
            </div>
            <div class="isp-accounts-filters">
                <div>
                    <label for="acc-from">From</label>
                    <input id="acc-from" type="date" wire:model.live="dateFrom" class="isp-accounts-filters__input" />
                </div>
                <div>
                    <label for="acc-to">To</label>
                    <input id="acc-to" type="date" wire:model.live="dateTo" class="isp-accounts-filters__input" />
                </div>
            </div>
        </section>

        <section class="isp-accounts-stats">
            <div class="isp-accounts-stat isp-accounts-stat--income">
                <span class="isp-accounts-stat__label">Income</span>
                <strong>{{ number_format($stats['income'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat isp-accounts-stat--expense">
                <span class="isp-accounts-stat__label">Expenses</span>
                <strong>{{ number_format($stats['expenses'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat isp-accounts-stat--primary">
                <span class="isp-accounts-stat__label">Net profit</span>
                <strong>{{ number_format($stats['net_profit'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Collections</span>
                <strong>{{ number_format($stats['collections'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Cashbook</span>
                <strong>{{ number_format($stats['cash_balance'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Bank</span>
                <strong>{{ number_format($stats['bank_balance'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Collector cash</span>
                <strong>{{ number_format($stats['collector_cash'], 2) }}</strong>
            </div>
            <div class="isp-accounts-stat">
                <span class="isp-accounts-stat__label">Reseller wallets</span>
                <strong>{{ number_format($stats['reseller_wallets'], 2) }}</strong>
            </div>
        </section>

        <p class="isp-accounts-period">Period: {{ $stats['period_label'] }}</p>

        <section class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ \App\Filament\Pages\AccountsWalletHubPage::getUrl() }}" class="rounded-xl border border-indigo-200 bg-white p-4 shadow-sm transition hover:border-indigo-400 dark:border-indigo-900/50 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">Wallets</p>
                <p class="mt-1 text-sm text-gray-500">Bank · cashbook · collector · reseller</p>
            </a>
            <a href="{{ \App\Filament\Pages\PaymentsReport::getUrl() }}" class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm transition hover:border-emerald-400 dark:border-emerald-900/50 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">Collection report</p>
                <p class="mt-1 text-sm text-gray-500">All payments &amp; bill statement rows</p>
            </a>
            <a href="{{ \App\Filament\Pages\PaymentsReport::getUrl() }}?gateway=bkash" class="rounded-xl border border-pink-200 bg-white p-4 shadow-sm transition hover:border-pink-400 dark:border-pink-900/50 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">bKash collections</p>
                <p class="mt-1 text-sm text-gray-500">Filter bKash only · export CSV</p>
            </a>
            <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="rounded-xl border border-teal-200 bg-white p-4 shadow-sm transition hover:border-teal-400 dark:border-teal-900/50 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">Bill collection desk</p>
                <p class="mt-1 text-sm text-gray-500">Receive cash / bKash at counter</p>
            </a>
        </section>
    </div>
</x-filament-panels::page>
