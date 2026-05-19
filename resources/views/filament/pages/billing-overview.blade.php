@php
    $stats = $this->getStats();
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <div class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-indigo-50/40 p-6 dark:border-violet-900/40 dark:from-violet-950/30 dark:via-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Billing center</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Auto invoices · pro-rata · VAT/SD · coupons · grace &amp; late fees · due reminders.
            </p>
            <div class="mt-4 flex flex-wrap gap-3 text-sm">
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">
                    <span class="text-violet-600 dark:text-violet-400">{{ $stats['open'] }}</span> open bills
                </span>
                <span class="rounded-full px-3 py-1 font-medium {{ $stats['overdue'] > 0 ? 'bg-rose-100 text-rose-800 dark:bg-rose-500/20 dark:text-rose-300' : 'bg-white shadow-sm dark:bg-gray-800 text-gray-700 dark:text-gray-300' }}">
                    {{ $stats['overdue'] }} overdue
                </span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800 text-gray-700 dark:text-gray-300">
                    {{ $stats['draft'] }} drafts
                </span>
                <span class="rounded-full bg-emerald-100 px-3 py-1 font-medium text-emerald-800 dark:bg-emerald-500/20 dark:text-emerald-300">
                    {{ number_format((float) $stats['collected_month'], 0) }} BDT collected (month)
                </span>
                <span class="rounded-full bg-amber-100 px-3 py-1 font-medium text-amber-900 dark:bg-amber-500/20 dark:text-amber-200">
                    {{ number_format((float) $stats['outstanding'], 0) }} BDT outstanding
                </span>
            </div>
        </div>

        @php $ops = $stats['ops'] ?? []; @endphp
        <motion.div class="flex flex-wrap items-center justify-between gap-2 px-1">
            <p class="text-sm text-gray-600 dark:text-gray-400">Dunning, credit limit, FUP, prepaid wallet</p>
            <a href="{{ \App\Filament\Pages\DunningReport::getUrl() }}" class="text-sm font-medium text-violet-600 hover:underline dark:text-violet-400">Dunning report →</a>
        </motion.div>
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Smart billing ops</h3>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60">
                    <p class="text-xs text-gray-500">Due tomorrow</p>
                    <p class="text-xl font-bold text-gray-900 dark:text-white">{{ $ops['due_tomorrow'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60">
                    <p class="text-xs text-gray-500">Over credit limit</p>
                    <p class="text-xl font-bold {{ ($ops['over_credit_limit'] ?? 0) > 0 ? 'text-rose-600' : 'text-gray-900 dark:text-white' }}">{{ $ops['over_credit_limit'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60">
                    <p class="text-xs text-gray-500">Prepaid expiring (7d)</p>
                    <p class="text-xl font-bold text-amber-700 dark:text-amber-300">{{ $ops['prepaid_expiring_7d'] ?? 0 }}</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60">
                    <p class="text-xs text-gray-500">AR 31–60 days</p>
                    <p class="text-lg font-bold">{{ number_format((float) ($ops['aging']['31_60']['amount'] ?? 0), 0) }} BDT</p>
                    <p class="text-xs text-gray-500">{{ $ops['aging']['31_60']['count'] ?? 0 }} invoices</p>
                </div>
                <div class="rounded-xl bg-gray-50 p-3 dark:bg-gray-800/60">
                    <p class="text-xs text-gray-500">AR 60+ days</p>
                    <p class="text-lg font-bold text-rose-700 dark:text-rose-300">{{ number_format((float) ($ops['aging']['60_plus']['amount'] ?? 0), 0) }} BDT</p>
                    <p class="text-xs text-gray-500">{{ $ops['aging']['60_plus']['count'] ?? 0 }} invoices</p>
                </div>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="isp-module-card group border-emerald-200/80 dark:border-emerald-900/40">
                <div class="flex items-start gap-3">
                    <span class="isp-module-icon text-emerald-600">
                        <x-filament::icon icon="heroicon-o-currency-bangladeshi" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-bold uppercase text-emerald-700 dark:text-emerald-300">Cashier</p>
                        <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">Bill collection desk</p>
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Search ID · phone · name · collect</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\InvoiceResource::getUrl('index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Invoices</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">All bills</p>
                <p class="mt-1 text-xs text-gray-500">Generate · print · late fee · coupon</p>
            </a>
            <a href="{{ \App\Filament\Resources\InvoiceResource::getUrl('create') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">New</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">Manual invoice</p>
                <p class="mt-1 text-xs text-gray-500">One-off charge or adjustment</p>
            </a>
            <a href="{{ \App\Filament\Resources\CouponResource::getUrl('index') }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Promotions</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">Coupons</p>
                <p class="mt-1 text-xs text-gray-500">Discount codes on bills</p>
            </a>
            <a href="{{ route('bill-payment.index') }}" target="_blank" class="rounded-xl border border-teal-200 bg-teal-50 p-4 shadow-sm transition hover:border-teal-400 dark:border-teal-900/50 dark:bg-teal-950/30">
                <p class="text-xs font-bold uppercase text-teal-700 dark:text-teal-300">Public</p>
                <p class="mt-1 font-semibold text-teal-900 dark:text-teal-100">/pay page</p>
                <p class="mt-1 text-xs text-teal-800/80">Customer self-pay</p>
            </a>
            <a href="{{ \App\Filament\Pages\CollectorMobile::getUrl() }}" class="rounded-xl border border-emerald-200 bg-white p-4 shadow-sm transition hover:border-emerald-400 dark:border-emerald-800 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-emerald-600">Field</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">Collector mobile</p>
                <p class="mt-1 text-xs text-gray-500">GPS collection · phone UI</p>
            </a>
            <a href="{{ \App\Filament\Pages\CollectorVisitsReport::getUrl() }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Reports</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">Collector visits map</p>
                <p class="mt-1 text-xs text-gray-500">GPS · leaderboard</p>
            </a>
            <a href="{{ \App\Filament\Pages\CollectionDeskReport::getUrl() }}" class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm transition hover:border-violet-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Today</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">Collection report</p>
            </a>
            <a href="{{ \App\Filament\Pages\GatewayReconciliationReport::getUrl() }}" class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 shadow-sm transition hover:border-amber-400 dark:border-amber-900/40 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-amber-700 dark:text-amber-300">Payments</p>
                <p class="mt-1 font-semibold text-gray-900 dark:text-white">Gateway reconciliation</p>
            </a>
        </div>

        <x-isp.hub-module-grid group="Billing" :skip-sections="['Hub']" />

        <details class="isp-ops-details">
            <summary class="isp-ops-details-summary">Scheduler &amp; CLI</summary>
            <div class="mt-3 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                <ul class="list-inside list-disc space-y-1 font-mono text-xs">
                    <li>php artisan isp:generate-bills [--force] [--dry-run] [--cycle=daily|hourly|monthly]</li>
                    <li>php artisan isp:apply-late-fees [--dry-run]</li>
                    <li>php artisan isp:send-invoice-due-reminders (smart dunning ladder)</li>
                    <li>php artisan isp:prepaid-wallet-settle</li>
                </ul>
                <p class="mt-3 text-xs">Packages set billing cycle; subscribers set billing mode, grace days, and billing day.</p>
            </div>
        </details>

        <x-isp.hub-footer />
    </div>
</x-filament-panels::page>
