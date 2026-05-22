@php
    $r = $this->getReport();
    $s = $r['summary'];
    $isPrint = request()->boolean('print');
@endphp
<x-filament-panels::page @class(['isp-reports-page', 'isp-reports-page--print' => $isPrint])>
    @unless ($isPrint)
        <div class="no-print mb-4 flex flex-wrap items-center gap-3">
            <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="text-sm text-primary-600 hover:underline">← Bill collection desk</a>
            <a href="{{ \App\Filament\Pages\CollectionDeskReport::getUrl(['preset' => 'today']) }}" class="text-sm text-gray-600 hover:underline">Collection report (GPS)</a>
        </div>
    @endunless

    <section class="isp-reports-hero mb-4">
        <div class="isp-reports-hero__main">
            <p class="isp-reports-hero__eyebrow">Billing · Collections</p>
            <h2 class="isp-reports-hero__title">Bill money trail</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                {{ $this->companyName() }} · {{ $r['from'] }} → {{ $r['to'] }}
                @if ($this->collectorId)
                    · Collector filter active
                @endif
            </p>
        </div>
    </section>

    @unless ($isPrint)
        <div class="no-print rounded-xl border border-violet-200 bg-violet-50/80 p-4 dark:border-violet-800 dark:bg-violet-950/40">
            <p class="text-sm font-semibold text-violet-900 dark:text-violet-200">কোথায় গেল টাকা — সংক্ষেপ</p>
            <p class="mt-1 text-xs text-violet-800/90 dark:text-violet-300">প্রতিটি সংগ্রহ: বিলে · wallet-এ · collector-এর হাতে · অফিসে জমা · খরচ। নিচের টেবিলে প্রতি payment-এর বিস্তারিত।</p>
        </div>

        <div class="no-print mt-4 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="mb-2 text-xs font-bold uppercase text-gray-500">Quick date</p>
            <div class="mb-3 flex flex-wrap gap-2">
                <button type="button" wire:click="setDatePreset('today')" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800">Today</button>
                <button type="button" wire:click="setDatePreset('yesterday')" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800">Yesterday</button>
                <button type="button" wire:click="setDatePreset('last7')" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800">Last 7 days</button>
                <button type="button" wire:click="setDatePreset('week')" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800">This week</button>
                <button type="button" wire:click="setDatePreset('month')" class="rounded-full border border-gray-300 px-3 py-1 text-xs font-semibold hover:bg-gray-100 dark:border-gray-600 dark:hover:bg-gray-800">This month</button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-gray-500">From date</label>
                    <input type="date" wire:model.live="dateFrom" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-gray-500">To date</label>
                    <input type="date" wire:model.live="dateTo" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Collector</label>
                    <select wire:model.live="collectorId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                        <option value="">All collectors</option>
                        @foreach ($this->getCollectorOptions() as $opt)
                            <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Search</label>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Name, ID, receipt…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
                </div>
            </div>
            @if ($this->canSeeCompanyExpenses())
                <label class="mt-3 flex items-center gap-2 text-sm">
                    <input type="checkbox" wire:model.live="includeCompanyExpenses" class="rounded border-gray-300" />
                    Include company vendor payments (accounts)
                </label>
            @endif
        </div>
    @endunless

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Total collected</p>
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($s['total_collected'], 2) }} BDT</p>
            <p class="text-xs text-gray-500">{{ $s['payment_count'] }} payments</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">To bills (invoice)</p>
            <p class="text-xl font-bold text-blue-700 dark:text-blue-400">{{ number_format($s['to_invoice'], 2) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">To subscriber wallet</p>
            <p class="text-xl font-bold text-amber-700 dark:text-amber-400">{{ number_format($s['to_wallet'], 2) }}</p>
            @if ($s['from_wallet'] > 0)
                <p class="text-xs text-gray-500">From wallet: {{ number_format($s['from_wallet'], 2) }}</p>
            @endif
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Field expenses (approved)</p>
            <p class="text-xl font-bold text-rose-700 dark:text-rose-400">{{ number_format($s['collector_expenses'], 2) }}</p>
            <p class="text-xs text-gray-500">Net after expenses: {{ number_format($s['net_after_expenses'], 2) }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-xl border overflow-hidden dark:border-gray-700">
        <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Money flow (period)</h3>
        <div class="divide-y dark:divide-gray-800">
            @foreach ($r['flow_steps'] as $step)
                <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
                    <div>
                        <p class="text-sm font-medium">{{ $step['label'] }}</p>
                        <p class="text-xs text-gray-500">{{ $step['hint'] }}</p>
                    </div>
                    <p class="text-lg font-bold tabular-nums">{{ number_format($step['amount'], 2) }} BDT</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border bg-white p-3 text-sm dark:border-gray-700 dark:bg-gray-900">
            <span class="text-gray-500">Still with collector (period unsettled)</span>
            <p class="font-bold">{{ number_format($s['field_in_collector_hand'], 2) }} BDT</p>
        </div>
        <div class="rounded-xl border bg-white p-3 text-sm dark:border-gray-700 dark:bg-gray-900">
            <span class="text-gray-500">Deposited in period</span>
            <p class="font-bold">{{ number_format($s['field_deposited_period'], 2) }} BDT</p>
        </div>
        @if ($this->collectorId)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-3 text-sm dark:border-amber-800 dark:bg-amber-950/30">
                <span class="text-amber-800 dark:text-amber-200">Current cash in hand (selected collector)</span>
                <p class="font-bold text-amber-900 dark:text-amber-100">{{ number_format($s['current_collector_cash'], 2) }} BDT</p>
            </div>
        @endif
    </div>

    @if (count($r['staff_expenses_by_category'] ?? []) > 0)
        <div class="mt-6 rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Staff expenses (vendor · office · other)</h3>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr class="border-b dark:border-gray-800">
                        <th class="px-4 py-2 text-left">Type · Category</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['staff_expenses_by_category'] as $row)
                        <tr class="border-t dark:border-gray-800">
                            <td class="px-4 py-2">{{ $row['category'] }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($row['total'], 2) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="px-4 py-2 text-xs text-gray-500">Total staff expenses: {{ number_format($s['staff_expenses'] ?? 0, 2) }} BDT</p>
        </div>
    @endif

    @if (count($r['expenses_by_category']) > 0)
        <div class="mt-6 rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Collector field expenses by category</h3>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr class="border-b dark:border-gray-800">
                        <th class="px-4 py-2 text-left">Category</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($r['expenses_by_category'] as $row)
                        <tr class="border-t dark:border-gray-800">
                            <td class="px-4 py-2">{{ $row['category'] }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($row['total'], 2) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $row['count'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($this->includeCompanyExpenses && count($r['vendor_expenses']) > 0)
        <div class="mt-4 rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Vendor payments</h3>
            <table class="w-full text-sm">
                <tbody>
                    @foreach ($r['vendor_expenses'] as $row)
                        <tr class="border-t dark:border-gray-800">
                            <td class="px-4 py-2">{{ $row['category'] }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($row['total'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="mt-6 overflow-x-auto rounded-xl border dark:border-gray-700 isp-reports-table-card">
        <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Each collection — allocation & cash status</h3>
        <table class="w-full min-w-[960px] text-sm">
            <thead class="text-xs uppercase text-gray-500">
                <tr class="border-b dark:border-gray-800">
                    <th class="px-3 py-2 text-left">Date</th>
                    <th class="px-3 py-2 text-left">Customer</th>
                    <th class="px-3 py-2 text-left">Collector</th>
                    <th class="px-3 py-2 text-right">Amount</th>
                    <th class="px-3 py-2 text-right">→ Bill</th>
                    <th class="px-3 py-2 text-right">→ Wallet</th>
                    <th class="px-3 py-2 text-left">Destination</th>
                    <th class="px-3 py-2 text-left">Cash / office</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($r['rows'] as $row)
                    <tr class="border-t dark:border-gray-800">
                        <td class="px-3 py-2 whitespace-nowrap">{{ $row['paid_at'] }}</td>
                        <td class="px-3 py-2">
                            <span class="font-medium">{{ $row['customer_name'] }}</span>
                            <span class="block text-xs text-gray-500">{{ $row['customer_code'] }}</span>
                        </td>
                        <td class="px-3 py-2 text-xs">{{ $row['collector_name'] }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ number_format($row['amount'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-blue-700 dark:text-blue-400">{{ number_format($row['to_invoice'], 2) }}</td>
                        <td class="px-3 py-2 text-right text-amber-700 dark:text-amber-400">{{ number_format($row['to_wallet'], 2) }}</td>
                        <td class="px-3 py-2 text-xs max-w-[200px]">{{ $row['destination'] }}</td>
                        <td class="px-3 py-2 text-xs">{{ $row['cash_status'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-gray-500">No payments in this range</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <p class="mt-4 text-xs text-gray-500">Generated {{ now()->format('Y-m-d H:i') }} · {{ auth()->user()?->name }}</p>

    @if ($isPrint)
        <script>
            window.addEventListener('load', () => window.print());
        </script>
    @endif
</x-filament-panels::page>
