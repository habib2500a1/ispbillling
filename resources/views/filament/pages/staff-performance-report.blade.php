@php
    $r = $this->getReport();
    $collection = $r['collection'] ?? [];
    $newLines = $r['new_lines'] ?? [];
    $activePreset = $this->activeDatePreset();
@endphp

<x-filament-panels::page class="isp-cdr-page">
    @include('filament.partials.ensure-route-stylesheet', ['file' => 'collection-desk-report-pro.css'])

    <div class="cdr-pro">
        <header class="cdr-hero">
            <div>
                <p class="cdr-hero__eyebrow">Billing · Staff KPIs</p>
                <h1 class="cdr-hero__title">Staff collection &amp; new lines</h1>
                <p class="cdr-hero__sub">
                    {{ $r['from'] }} → {{ $r['to'] }}
                    · {{ number_format($collection['count'] ?? 0) }} payment{{ ($collection['count'] ?? 0) === 1 ? '' : 's' }}
                    · {{ number_format($newLines['total'] ?? 0) }} new line{{ ($newLines['total'] ?? 0) === 1 ? '' : 's' }}
                </p>
                <div class="cdr-hero__stats">
                    <span class="cdr-hero__stat">
                        <strong>{{ number_format($collection['total'] ?? 0, 0) }}</strong>
                        Staff collection (BDT)
                    </span>
                    <span class="cdr-hero__stat">
                        <strong>{{ number_format($newLines['total'] ?? 0) }}</strong>
                        New subscribers
                    </span>
                </div>
                @if ($r['legacy_portal'] ?? false)
                    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                        Includes collections imported from {{ \App\Support\BillingPortalLabel::name() }} (staff name from <em>Received by</em>).
                        Use <strong>Sync from portal</strong> if today’s total looks low.
                    </p>
                @endif
            </div>
            <div class="cdr-hero__actions">
                @foreach ($this->getCachedHeaderActions() as $action)
                    {{ $action }}
                @endforeach
            </div>
        </header>

        <section class="cdr-filters">
            <div class="mb-3 flex flex-wrap gap-2">
                @foreach (['today' => 'Today', 'week' => 'This week', 'month' => 'This month'] as $key => $label)
                    <button type="button" wire:click="setDatePreset('{{ $key }}')" @class([
                        'cdr-preset',
                        'cdr-preset--active' => $activePreset === $key,
                    ])>{{ $label }}</button>
                @endforeach
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="cdr-field">
                    <label>From date</label>
                    <input type="date" wire:model.live="dateFrom" />
                </div>
                <div class="cdr-field">
                    <label>To date</label>
                    <input type="date" wire:model.live="dateTo" />
                </div>
                <div class="cdr-field">
                    <label>Staff filter</label>
                    @if ($this->isStaffCollectorReportScoped())
                        <p class="rounded-lg border px-3 py-2 text-sm font-semibold">{{ $this->scopedCollectorDisplayName() }}</p>
                    @else
                        <select wire:model.live="collectorId">
                            <option value="">All staff</option>
                            @foreach (app(\App\Services\Collector\CollectorStaffResolver::class)->collectableStaffOptions() as $id => $name)
                                <option value="{{ $id }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            </div>
        </section>

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="cdr-table-wrap">
                <h2 class="cdr-table-title">Collection by staff</h2>
                <table class="cdr-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th class="text-right">Payments</th>
                            <th class="text-right">Collected (BDT)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($collection['by_staff'] ?? [] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right">{{ number_format($row['count']) }}</td>
                                <td class="text-right font-semibold">{{ number_format($row['total'], 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-center text-gray-500">No collections in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>

            <section class="cdr-table-wrap">
                <h2 class="cdr-table-title">New line performance</h2>
                <table class="cdr-table">
                    <thead>
                        <tr>
                            <th>Staff</th>
                            <th class="text-right">New lines</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($newLines['by_staff'] ?? [] as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td>
                                <td class="text-right font-semibold">{{ number_format($row['count']) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="2" class="text-center text-gray-500">No new subscribers in this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </section>
        </div>
    </div>
</x-filament-panels::page>
