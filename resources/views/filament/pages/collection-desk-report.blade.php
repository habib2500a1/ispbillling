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
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
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
            <div>
                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Payment method</label>
                <select wire:model.live="methodFilter" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                    <option value="all">All methods</option>
                    <option value="bkash">bKash (all)</option>
                    <option value="cash">Cash</option>
                    <option value="bank">Bank</option>
                    <option value="nagad">Nagad</option>
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Search name, PPP, staff, invoice, bKash TRX</label>
                <input type="search" wire:model.live.debounce.400ms="search" placeholder="Habib, iman1.kp, bKash, invoice…" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" />
            </div>
        </div>
        <div class="mt-4 border-t border-gray-200 pt-4 dark:border-gray-700">
            <p class="mb-2 text-xs font-bold uppercase text-gray-500">Collection source</p>
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="$set('sourceFilter', 'legacy_portal')" @class([
                    'rounded-lg px-3 py-1.5 text-xs font-semibold',
                    'bg-teal-600 text-white' => $sourceFilter === 'legacy_portal',
                    'border border-gray-300 dark:border-gray-600' => $sourceFilter !== 'legacy_portal',
                ])>{{ \App\Support\BillingPortalLabel::collectionFilter() }}</button>
                <button type="button" wire:click="$set('sourceFilter', 'desk')" @class([
                    'rounded-lg px-3 py-1.5 text-xs font-semibold',
                    'bg-teal-600 text-white' => $sourceFilter === 'desk',
                    'border border-gray-300 dark:border-gray-600' => $sourceFilter !== 'desk',
                ])>This system (desk only)</button>
                <button type="button" wire:click="$set('sourceFilter', 'all')" @class([
                    'rounded-lg px-3 py-1.5 text-xs font-semibold',
                    'bg-teal-600 text-white' => $sourceFilter === 'all',
                    'border border-gray-300 dark:border-gray-600' => $sourceFilter !== 'all',
                ])>All combined</button>
            </div>
            <p class="mt-2 text-xs text-gray-500">
                {{ \App\Support\BillingPortalLabel::name() }} = imported online payments (old billing portal).
                Desk = cash/bKash entered only in this app.
            </p>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-sky-200 bg-sky-50/50 p-4 dark:border-sky-900/40 dark:bg-sky-950/20">
            <p class="text-xs uppercase text-sky-800 dark:text-sky-200">{{ \App\Support\BillingPortalLabel::name() }} (import)</p>
            <p class="text-xl font-bold text-sky-900 dark:text-sky-100">{{ number_format($r['legacy_portal_total'], 2) }} BDT</p>
            <p class="text-xs text-sky-800/80">{{ $r['legacy_portal_count'] }} in period</p>
        </div>
        <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
            <p class="text-xs uppercase text-amber-800 dark:text-amber-200">This system (desk)</p>
            <p class="text-xl font-bold text-amber-900 dark:text-amber-100">{{ number_format($r['desk_total'], 2) }} BDT</p>
            <p class="text-xs text-amber-800/80">{{ $r['desk_count'] }} in period</p>
        </div>
        @if ($r['isp_grid_collected_mtd'] !== null)
            <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
                <p class="text-xs uppercase text-violet-800 dark:text-violet-200">ISD billing grid (MTD)</p>
                <p class="text-xl font-bold text-violet-900 dark:text-violet-100">{{ number_format($r['isp_grid_collected_mtd'], 2) }} BDT</p>
                <p class="text-xs text-violet-800/80">Dashboard collected</p>
            </div>
        @endif
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Showing ({{ $r['source_filter'] }})</p>
            <p class="text-2xl font-bold text-emerald-700 dark:text-emerald-400">{{ number_format($r['total'], 2) }} BDT</p>
            <p class="text-xs text-gray-500">{{ $r['count'] }} payment{{ $r['count'] === 1 ? '' : 's' }}</p>
        </div>
    </div>

    <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
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
            Collection detail — কে কালেক্ট করেছে, invoice, bKash/Cash
        </h3>
        <div class="max-h-[36rem] overflow-auto">
            <table class="min-w-full text-sm cdr-table">
                <thead class="sticky top-0 z-10 bg-slate-800 text-xs uppercase tracking-wide text-white">
                    <tr>
                        <th class="px-2 py-2.5 text-left whitespace-nowrap">Date &amp; time</th>
                        <th class="px-2 py-2.5 text-left">Bill #</th>
                        <th class="px-2 py-2.5 text-left">User name</th>
                        <th class="px-2 py-2.5 text-left">Full name</th>
                        <th class="px-2 py-2.5 text-left">Phone</th>
                        <th class="px-2 py-2.5 text-left">Note</th>
                        <th class="px-2 py-2.5 text-right">Total</th>
                        <th class="px-2 py-2.5 text-right">Received</th>
                        <th class="px-2 py-2.5 text-right">Discount</th>
                        <th class="px-2 py-2.5 text-right">Balance</th>
                        <th class="px-2 py-2.5 text-left">Method</th>
                        <th class="px-2 py-2.5 text-left">Received by</th>
                        <th class="px-2 py-2.5 text-left">Approved by</th>
                        <th class="px-2 py-2.5 text-left">Created by</th>
                        <th class="px-2 py-2.5 text-center">GPS</th>
                        <th class="px-2 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($r['rows'] as $row)
                        <tr class="border-t border-gray-200 odd:bg-white even:bg-gray-50/60 hover:bg-violet-50/50 dark:border-gray-800 dark:odd:bg-gray-900 dark:even:bg-gray-900/70 dark:hover:bg-gray-800/80">
                            <td class="px-2 py-2 whitespace-nowrap text-xs">
                                <span class="block font-semibold">{{ $row['date'] }}</span>
                                <span class="text-gray-500">{{ $row['time'] }}</span>
                            </td>
                            <td class="px-2 py-2 font-mono text-xs">{{ $row['bill_number'] }}</td>
                            <td class="px-2 py-2 font-mono text-xs">{{ $row['username'] }}</td>
                            <td class="px-2 py-2 font-medium">{{ $row['customer_name'] }}</td>
                            <td class="px-2 py-2 text-xs whitespace-nowrap">{{ $row['customer_phone'] }}</td>
                            <td class="px-2 py-2 text-xs max-w-[6rem] truncate" title="{{ $row['notes'] }}">{{ $row['notes'] ?: '—' }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ number_format($row['bill_total'], 0) }}</td>
                            <td class="px-2 py-2 text-right font-mono font-semibold">{{ number_format($row['amount'], 0) }}</td>
                            <td class="px-2 py-2 text-right font-mono text-amber-700 dark:text-amber-400">{{ number_format($row['discount'], 0) }}</td>
                            <td class="px-2 py-2 text-right font-mono {{ $row['balance_due'] > 0 ? 'text-rose-600' : 'text-gray-500' }}">{{ number_format($row['balance_due'], 2) }}</td>
                            <td class="px-2 py-2 whitespace-nowrap">
                                <span @class([
                                    'inline-flex rounded-md px-2 py-0.5 text-xs font-bold',
                                    'bg-pink-100 text-pink-800 dark:bg-pink-950 dark:text-pink-200' => $row['is_bkash'],
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' => ($row['method'] ?? '') === 'cash',
                                    'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' => ! $row['is_bkash'] && ($row['method'] ?? '') !== 'cash',
                                ])>{{ $row['method_label'] }}</span>
                            </td>
                            <td class="px-2 py-2 text-xs font-semibold">{{ $row['received_by'] }}</td>
                            <td class="px-2 py-2 text-xs">{{ $row['approved_by'] }}</td>
                            <td class="px-2 py-2 text-xs">{{ $row['created_by'] }}</td>
                            <td class="px-2 py-2 text-center">
                                @if ($row['has_gps'])
                                    <a href="https://maps.google.com/?q={{ $row['latitude'] }},{{ $row['longitude'] }}" target="_blank" class="text-teal-600 hover:underline" title="Map">✓</a>
                                @else
                                    <span class="text-gray-300">—</span>
                                @endif
                            </td>
                            <td class="px-2 py-2 text-right whitespace-nowrap text-xs">
                                <a href="{{ $row['receipt_url'] }}" target="_blank" class="font-semibold text-violet-600 hover:underline">Receipt</a>
                                @if ($row['invoice_number'])
                                    <span class="block text-gray-500" title="{{ $row['invoice_number'] }}">Inv</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="16" class="px-4 py-12 text-center text-gray-500">
                                No collections found — try <strong>All combined</strong> or search by staff name (Habib, admin).
                            </td>
                        </tr>
                    @endforelse
                </tbody>
                @if (($r['count'] ?? 0) > 0)
                    <tfoot class="sticky bottom-0 bg-slate-100 font-bold dark:bg-slate-900">
                        <tr>
                            <td colspan="6" class="px-2 py-2 text-right uppercase text-xs text-gray-600">Total</td>
                            <td class="px-2 py-2 text-right font-mono">{{ number_format($r['row_totals']['bill_total'] ?? 0, 0) }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ number_format($r['row_totals']['received'] ?? 0, 0) }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ number_format($r['row_totals']['discount'] ?? 0, 0) }}</td>
                            <td class="px-2 py-2 text-right font-mono">{{ number_format($r['row_totals']['balance_due'] ?? 0, 2) }}</td>
                            <td colspan="6"></td>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
        <p class="border-t border-gray-200 px-4 py-2 text-xs text-gray-500 dark:border-gray-700">
            <strong>Received by</strong> = staff/admin who took payment (desk user or legacy import name).
            <strong>bKash</strong> shows pink badge. Search: name, PPP login, Habib, bKash TRX, invoice #.
        </p>
    </div>
</x-filament-panels::page>
