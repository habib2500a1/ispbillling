@php $r = $this->getReport(); @endphp
<x-filament-panels::page>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="text-sm text-primary-600 hover:underline">← Bill collection desk</a>
        <a href="{{ \App\Filament\Pages\BillingFundFlowReport::getUrl() }}" class="text-sm text-violet-600 hover:underline">Bill money trail (cost breakdown) →</a>
        <a href="{{ \App\Filament\Pages\CollectorVisitsReport::getUrl() }}" class="text-sm text-teal-600 hover:underline">Collector visits (GPS) →</a>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
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
                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Collector (user)</label>
                <select wire:model.live="collectorId" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="">All collectors</option>
                    @foreach ($this->getCollectorOptions() as $opt)
                        <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Search name, ID, phone, receipt, TRX</label>
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Type to filter…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
            </div>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Total collected</p>
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($r['total'], 2) }} BDT</p>
            <p class="text-xs text-gray-500">{{ $r['count'] }} payment{{ $r['count'] === 1 ? '' : 's' }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Period</p>
            <p class="text-sm font-semibold">{{ $r['from'] }} → {{ $r['to'] }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Cash / bank</p>
            <p class="text-lg font-bold">{{ number_format($r['cash_total'], 2) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Online gateways</p>
            <p class="text-lg font-bold">{{ number_format($r['online_total'], 2) }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">With GPS tag</p>
            <p class="text-lg font-bold">{{ $r['with_gps'] }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">By payment method</h3>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr class="border-b dark:border-gray-800">
                        <th class="px-4 py-2 text-left">Method</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['by_method'] as $method => $row)
                        <tr class="border-t dark:border-gray-800">
                            <td class="px-4 py-2">{{ \App\Support\PaymentGateway::label($method) }}</td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($row['total'], 2) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $row['count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No payments in range</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">By collector (staff user)</h3>
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr class="border-b dark:border-gray-800">
                        <th class="px-4 py-2 text-left">Name</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                        <th class="px-4 py-2 text-right">Count</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['by_collector'] as $row)
                        <tr class="border-t dark:border-gray-800">
                            <td class="px-4 py-2">
                                @if ($row['collector_id'])
                                    <button type="button" wire:click="$set('collectorId', {{ $row['collector_id'] }})" class="font-medium text-violet-600 hover:underline">{{ $row['collector'] }}</button>
                                @else
                                    {{ $row['collector'] }}
                                @endif
                            </td>
                            <td class="px-4 py-2 text-right font-medium">{{ number_format($row['total'], 2) }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $row['count'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">—</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-xl border overflow-hidden dark:border-gray-700">
        <h3 class="bg-gray-50 px-4 py-3 text-sm font-semibold dark:bg-gray-800">
            Collection detail — date, collector, customer name &amp; ID
        </h3>
        <div class="max-h-[32rem] overflow-auto">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Date / time</th>
                        <th class="px-3 py-2 text-left">Receipt</th>
                        <th class="px-3 py-2 text-left">Collector</th>
                        <th class="px-3 py-2 text-left">Customer name</th>
                        <th class="px-3 py-2 text-left">ID / phone</th>
                        <th class="px-3 py-2 text-left">Invoice</th>
                        <th class="px-3 py-2 text-left whitespace-nowrap">Valid / off</th>
                        <th class="px-3 py-2 text-right">Amount</th>
                        <th class="px-3 py-2 text-left">Method</th>
                        <th class="px-3 py-2 text-left">Reference</th>
                        <th class="px-3 py-2 text-center">GPS</th>
                        <th class="px-3 py-2 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['rows'] as $row)
                        <tr class="border-t dark:border-gray-800 hover:bg-gray-50/80 dark:hover:bg-gray-800/50">
                            <td class="px-3 py-2 whitespace-nowrap">
                                <span class="font-medium">{{ $row['date'] }}</span>
                                <span class="text-gray-500">{{ $row['time'] }}</span>
                            </td>
                            <td class="px-3 py-2 font-mono text-xs">{{ $row['receipt_number'] }}</td>
                            <td class="px-3 py-2">
                                <span class="font-medium">{{ $row['collector_name'] }}</span>
                                @if ($row['collector_email'])
                                    <span class="block text-xs text-gray-500">{{ $row['collector_email'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 font-medium">{{ $row['customer_name'] }}</td>
                            <td class="px-3 py-2 text-xs">
                                <span class="font-mono">{{ $row['customer_code'] }}</span>
                                @if ($row['customer_phone'] !== '—')
                                    <span class="block text-gray-500">{{ $row['customer_phone'] }}</span>
                                @endif
                                @if (! empty($row['customer_area']))
                                    <span class="block text-gray-400">{{ $row['customer_area'] }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-xs">{{ $row['invoice_number'] ?? '—' }}</td>
                            <td class="px-3 py-2 text-xs">
                                @if ($row['service_valid_until'])
                                    <span class="block">বৈধ: {{ $row['service_valid_until'] }}</span>
                                    <span class="block {{ ($row['days_until_off'] ?? 1) < 0 ? 'text-rose-600' : 'text-amber-700' }}">
                                        Off: {{ $row['service_off_date'] }}
                                    </span>
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right font-bold whitespace-nowrap">{{ number_format($row['amount'], 2) }}</td>
                            <td class="px-3 py-2">{{ $row['method_label'] }}</td>
                            <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400 max-w-[8rem] truncate" title="{{ $row['reference'] ?? $row['gateway_transaction_id'] }}">
                                {{ $row['reference'] ?: ($row['gateway_transaction_id'] ?: '—') }}
                            </td>
                            <td class="px-3 py-2 text-center">
                                @if ($row['has_gps'])
                                    <a href="https://maps.google.com/?q={{ $row['latitude'] }},{{ $row['longitude'] }}" target="_blank" class="text-teal-600 hover:underline" title="View on map">✓</a>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap">
                                <a href="{{ $row['receipt_url'] }}" target="_blank" class="text-xs font-semibold text-violet-600 hover:underline">Receipt</a>
                                <span class="text-gray-300">·</span>
                                <a href="{{ $row['edit_url'] }}" class="text-xs font-semibold text-amber-700 hover:underline">Edit pay</a>
                                @if ($row['subscriber_edit_url'])
                                    <span class="text-gray-300">·</span>
                                    <a href="{{ $row['subscriber_edit_url'] }}" class="text-xs font-semibold text-teal-600 hover:underline">Subscriber</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="px-4 py-12 text-center text-gray-500">
                                No collections found for this period or filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
