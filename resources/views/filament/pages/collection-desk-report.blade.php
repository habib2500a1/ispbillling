@php
    $r = $this->getReport();
    $activePreset = $this->activeDatePreset();
@endphp

<x-filament-panels::page class="isp-cdr-page">
    <div class="cdr-pro">
        <header class="cdr-hero">
            <div>
                <p class="cdr-hero__eyebrow">Billing · Collections</p>
                <h1 class="cdr-hero__title">Collection report</h1>
                <p class="cdr-hero__sub">
                    {{ $r['from'] }} → {{ $r['to'] }} · {{ number_format($r['count']) }} payment{{ $r['count'] === 1 ? '' : 's' }}
                </p>
                <div class="cdr-hero__stats">
                    <span class="cdr-hero__stat">
                        <strong>{{ number_format($r['total'], 0) }}</strong>
                        Total received (BDT)
                    </span>
                    <span class="cdr-hero__stat">
                        <strong>{{ number_format($r['cash_total'], 0) }}</strong>
                        Cash / bank
                    </span>
                    <span class="cdr-hero__stat">
                        <strong>{{ number_format($r['online_total'], 0) }}</strong>
                        bKash / online
                    </span>
                </div>
            </div>
            <div class="cdr-hero__actions">
                @foreach ($this->getCachedHeaderActions() as $action)
                    {{ $action }}
                @endforeach
                <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="cdr-preset">Collection desk</a>
            </div>
        </header>

        <section class="cdr-filters">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach ([
                    'today' => 'Today',
                    'yesterday' => 'Yesterday',
                    'last7' => 'Last 7 days',
                    'week' => 'This week',
                    'month' => 'This month',
                ] as $key => $label)
                    <button type="button" wire:click="setDatePreset('{{ $key }}')" @class([
                        'cdr-preset',
                        'cdr-preset--active' => $activePreset === $key,
                    ])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <div class="cdr-field">
                    <label>From date</label>
                    <input type="date" wire:model.live="dateFrom" />
                </div>
                <div class="cdr-field">
                    <label>To date</label>
                    <input type="date" wire:model.live="dateTo" />
                </div>
                <div class="cdr-field">
                    <label>Collector (staff)</label>
                    @if ($this->isStaffCollectorReportScoped())
                        <p class="rounded-lg border px-3 py-2 text-sm font-semibold">{{ $this->scopedCollectorDisplayName() }}</p>
                    @else
                        <select wire:model.live="collectorId">
                            <option value="">All collectors</option>
                            @foreach ($this->getCollectorOptions() as $opt)
                                <option value="{{ $opt['id'] }}">{{ $opt['name'] }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
                <div class="cdr-field">
                    <label>Payment method</label>
                    <select wire:model.live="methodFilter">
                        <option value="all">All</option>
                        <option value="bkash">bKash</option>
                        <option value="cash">Cash</option>
                        <option value="bank">Bank</option>
                        <option value="nagad">Nagad</option>
                    </select>
                </div>
                <div class="cdr-field sm:col-span-2">
                    <label>Search — name, PPP, staff (Habib), invoice, bKash TRX</label>
                    <input type="search" wire:model.live.debounce.400ms="search" placeholder="Type to filter…" />
                </div>
            </div>
            <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-gray-200 pt-3 dark:border-gray-700">
                <span class="text-xs font-bold uppercase text-gray-500">Source:</span>
                @foreach ([
                    'all' => 'All combined',
                    'legacy_portal' => \App\Support\BillingPortalLabel::collectionFilter(),
                    'desk' => 'Desk only',
                ] as $key => $label)
                    <button type="button" wire:click="$set('sourceFilter', '{{ $key }}')" @class([
                        'cdr-source-btn',
                        'cdr-source-btn--active' => $sourceFilter === $key,
                    ])>{{ $label }}</button>
                @endforeach
            </div>
        </section>

        <section class="cdr-grid-wrap">
            <div class="cdr-grid-scroll">
                <table class="cdr-table">
                    <thead>
                        <tr>
                            <th>Date &amp; time</th>
                            <th>Bill #</th>
                            <th>User name</th>
                            <th>Full name</th>
                            <th>Phone number</th>
                            <th>Note / remarks</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Received</th>
                            <th class="text-right">VAT</th>
                            <th class="text-right">Discount</th>
                            <th class="text-right">Balance</th>
                            <th>Payment method</th>
                            <th>Received by</th>
                            <th>Approved by</th>
                            <th>Created by</th>
                            <th class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($r['rows'] as $row)
                            <tr>
                                <td class="whitespace-nowrap">
                                    <span class="font-semibold">{{ $row['date'] }}</span>
                                    <span class="block text-xs opacity-70">{{ $row['time'] }}</span>
                                </td>
                                <td class="font-mono text-xs">{{ $row['bill_number'] }}</td>
                                <td class="font-mono text-xs">{{ $row['username'] }}</td>
                                <td class="font-medium">{{ $row['customer_name'] }}</td>
                                <td class="whitespace-nowrap text-xs">{{ $row['customer_phone'] }}</td>
                                <td class="max-w-[7rem] truncate text-xs" title="{{ $row['notes'] }}">{{ $row['notes'] ?: '—' }}</td>
                                <td class="text-right">{{ number_format($row['bill_total'], 0) }}</td>
                                <td class="text-right font-semibold">{{ number_format($row['amount'], 0) }}</td>
                                <td class="text-right">{{ number_format($row['vat'] ?? 0, 0) }}</td>
                                <td class="text-right">{{ number_format($row['discount'], 0) }}</td>
                                <td class="text-right {{ $row['balance_due'] > 0 ? 'text-rose-600 dark:text-rose-400' : '' }}">{{ number_format($row['balance_due'], 2) }}</td>
                                <td>
                                    <span @class([
                                        'cdr-method',
                                        'cdr-method--bkash' => $row['is_bkash'],
                                        'cdr-method--cash' => ($row['method'] ?? '') === 'cash',
                                    ])>{{ $row['method_label'] }}</span>
                                </td>
                                <td><span class="cdr-staff">{{ $row['received_by'] }}</span></td>
                                <td class="text-xs">{{ $row['approved_by'] }}</td>
                                <td class="text-xs">{{ $row['created_by'] }}</td>
                                <td class="text-center">
                                    <a href="{{ $row['receipt_url'] }}" target="_blank" class="cdr-ok" title="Receipt">✓</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="16" class="py-12 text-center text-gray-500">
                                    No collections — select <strong>All combined</strong> or widen dates.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if (($r['count'] ?? 0) > 0)
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-right">Total</td>
                                <td class="text-right">{{ number_format($r['row_totals']['bill_total'] ?? 0, 0) }}</td>
                                <td class="text-right">{{ number_format($r['row_totals']['received'] ?? 0, 0) }}</td>
                                <td class="text-right">0</td>
                                <td class="text-right">{{ number_format($r['row_totals']['discount'] ?? 0, 0) }}</td>
                                <td class="text-right">{{ number_format($r['row_totals']['balance_due'] ?? 0, 2) }}</td>
                                <td colspan="5"></td>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </section>

        @if (count($r['by_method'] ?? []) > 0 || count($r['by_collector'] ?? []) > 0)
            <div class="cdr-mini-tables">
                <div class="cdr-mini">
                    <h3>By payment method</h3>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($r['by_method'] as $method => $row)
                                <tr class="border-t dark:border-gray-800">
                                    <td class="px-3 py-2">{{ \App\Support\PaymentGateway::label($method) }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ number_format($row['total'], 0) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="cdr-mini">
                    <h3>By collector</h3>
                    <table class="w-full text-sm">
                        <tbody>
                            @foreach ($r['by_collector'] as $row)
                                <tr class="border-t dark:border-gray-800">
                                    <td class="px-3 py-2">
                                        @if ($row['collector_id'] && ! $this->isStaffCollectorReportScoped())
                                            <button type="button" wire:click="$set('collectorId', {{ $row['collector_id'] }})" class="font-medium text-violet-600 hover:underline">{{ $row['collector'] }}</button>
                                        @else
                                            {{ $row['collector'] }}
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-right font-medium">{{ number_format($row['total'], 0) }}</td>
                                    <td class="px-3 py-2 text-right text-gray-500">{{ $row['count'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
