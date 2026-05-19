@php
    $report = $this->getReportData();
    $summary = $report['summary'];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="text-sm text-indigo-600 hover:underline">&larr; Reports hub</a>
            <form wire:submit.prevent class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                {{ $this->form }}
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Collected (period)</p>
                <p class="text-xl font-bold text-emerald-600">{{ number_format($summary['collected'], 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Collection rate</p>
                <p class="text-xl font-bold text-indigo-600">{{ $summary['collection_rate'] }}%</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Outstanding (now)</p>
                <p class="text-xl font-bold text-rose-600">{{ number_format($summary['outstanding'], 2) }} BDT</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs uppercase text-gray-500">Churned (period)</p>
                <p class="text-xl font-bold text-rose-700">−{{ $report['churn']['totals']['churned'] }}</p>
            </div>
        </div>

        <div class="flex flex-wrap gap-2 border-b border-gray-200 pb-2 dark:border-gray-700">
            <button type="button" wire:click="setActiveTab('zones')" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $activeTab === 'zones' ? 'bg-cyan-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">Zone collection</button>
            <button type="button" wire:click="setActiveTab('churn')" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $activeTab === 'churn' ? 'bg-rose-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">Churn by zone</button>
        </div>

        @if ($activeTab === 'zones')
            @php
                $zones = $report['zones'];
                $totalCollected = collect($zones)->sum('collected');
                $totalInvoiced = collect($zones)->sum('invoiced');
            @endphp
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold dark:text-white">Zone-wise collection</h3>
                <p class="text-sm text-gray-500">{{ $report['from']->format('d M Y') }} – {{ $report['to']->format('d M Y') }} · {{ number_format($totalCollected, 2) }} BDT collected / {{ number_format($totalInvoiced, 2) }} invoiced</p>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full min-w-[720px] text-left text-sm">
                        <thead class="border-b dark:border-gray-700">
                            <tr>
                                <th class="py-2">Area</th>
                                <th class="py-2">Zone</th>
                                <th class="py-2 text-right">Subs</th>
                                <th class="py-2 text-right">Active</th>
                                <th class="py-2 text-right">Invoiced</th>
                                <th class="py-2 text-right">Collected</th>
                                <th class="py-2 text-right">Rate</th>
                                <th class="py-2 text-right">Due now</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($zones as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $row['area'] }}</td>
                                    <td class="py-2 font-medium">{{ $row['zone'] }}</td>
                                    <td class="py-2 text-right">{{ $row['subscribers'] }}</td>
                                    <td class="py-2 text-right">{{ $row['active'] }}</td>
                                    <td class="py-2 text-right tabular-nums">{{ number_format($row['invoiced'], 2) }}</td>
                                    <td class="py-2 text-right font-semibold text-emerald-700 tabular-nums">{{ number_format($row['collected'], 2) }}</td>
                                    <td class="py-2 text-right">{{ $row['collection_rate'] }}%</td>
                                    <td class="py-2 text-right text-rose-600 tabular-nums">{{ number_format($row['outstanding'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="py-8 text-center text-gray-500">No zones with subscribers.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($activeTab === 'churn')
            @php $churn = $report['churn']; @endphp
            <div class="grid gap-6 lg:grid-cols-2">
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="text-lg font-semibold dark:text-white">Churn by zone</h3>
                    <p class="text-sm text-gray-500">
                        {{ $churn['totals']['suspended'] }} suspended · {{ $churn['totals']['terminated'] }} terminated · {{ $churn['totals']['expired'] }} expired
                    </p>
                    <table class="mt-4 w-full text-left text-sm">
                        <thead class="border-b dark:border-gray-700">
                            <tr><th class="py-2">Area</th><th class="py-2">Zone</th><th class="py-2 text-right">Total</th></tr>
                        </thead>
                        <tbody>
                            @forelse ($churn['by_zone'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $row['area'] }}</td>
                                    <td class="py-2">{{ $row['zone'] }}</td>
                                    <td class="py-2 text-right font-bold text-rose-700">{{ $row['churned'] }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="py-6 text-center text-gray-500">No churn in this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                    <h3 class="text-lg font-semibold dark:text-white">Recent churned subscribers</h3>
                    <div class="mt-4 max-h-96 overflow-y-auto">
                        <table class="w-full text-left text-sm">
                            <thead class="border-b dark:border-gray-700">
                                <tr><th class="py-2">Code</th><th class="py-2">Name</th><th class="py-2">Zone</th><th class="py-2">Status</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($churn['recent'] as $row)
                                    <tr class="border-b border-gray-100 dark:border-gray-800">
                                        <td class="py-2 font-mono text-xs">{{ $row['customer_code'] }}</td>
                                        <td class="py-2">{{ $row['name'] }}</td>
                                        <td class="py-2 text-xs">{{ $row['zone'] }}</td>
                                        <td class="py-2 capitalize">{{ $row['status'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-6 text-center text-gray-500">No records</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
