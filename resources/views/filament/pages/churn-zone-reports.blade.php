@php
    $report = $this->getReportData();
    $summary = $report['summary'];
@endphp

<x-filament-panels::page>
    <div class="isp-bi-page">
        <x-isp.reports.page-header
            eyebrow="Customer analytics"
            title="Churn &amp; zone collection"
            :subtitle="$report['from']->format('d M Y').' – '.$report['to']->format('d M Y').' · Zone recovery and churn by geography'"
            score-label="Collection rate"
            :score-value="$summary['collection_rate'].'%'"
        />

        <div class="isp-bi-toolbar">
            <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="isp-bi-back">← Intelligence center</a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'churn']) }}" class="isp-bi-back">Analytics churn tab →</a>
        </div>

        <div class="isp-bi-filters">
            <div class="flex flex-wrap gap-2">
                <button type="button" wire:click="applyDatePreset('today')" class="isp-bi-preset">Today</button>
                <button type="button" wire:click="applyDatePreset('week')" class="isp-bi-preset">This week</button>
                <button type="button" wire:click="applyDatePreset('month')" class="isp-bi-preset">This month</button>
                <button type="button" wire:click="applyDatePreset('year')" class="isp-bi-preset">This year</button>
            </div>
            <div class="flex-1 min-w-[14rem]">{{ $this->form }}</div>
        </div>

        <div class="isp-bi-kpi-grid isp-bi-kpi-grid--4">
            <x-isp.reports.kpi-card label="Collected (period)" :value="number_format($summary['collected'], 2).' BDT'" tone="emerald" />
            <x-isp.reports.kpi-card label="Collection rate" :value="$summary['collection_rate'].'%'" tone="violet" />
            <x-isp.reports.kpi-card label="Outstanding (now)" :value="number_format($summary['outstanding'], 2).' BDT'" tone="rose" />
            <x-isp.reports.kpi-card label="Churned (period)" :value="'−'.$report['churn']['totals']['churned']" tone="amber" />
        </div>

        <section class="isp-bi-section">
            <div class="isp-bi-section__body space-y-3">
                <div class="isp-bi-tabs">
                    <button type="button" wire:click="setActiveTab('zones')" class="isp-bi-tab {{ $activeTab === 'zones' ? 'is-active' : '' }}">Zone collection</button>
                    <button type="button" wire:click="setActiveTab('churn')" class="isp-bi-tab {{ $activeTab === 'churn' ? 'is-active' : '' }}">Churn by zone</button>
                </div>
            </div>
        </section>

        @if ($activeTab === 'zones')
            @php
                $zones = $report['zones'];
                $totalCollected = collect($zones)->sum('collected');
                $totalInvoiced = collect($zones)->sum('invoiced');
            @endphp
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Zone-wise collection</h2>
                        <p class="isp-bi-section__desc">{{ number_format($totalCollected, 2) }} BDT collected / {{ number_format($totalInvoiced, 2) }} invoiced</p>
                    </div>
                </div>
                <div class="isp-bi-section__body isp-bi-table-wrap">
                    <table class="isp-bi-table">
                        <thead>
                            <tr>
                                <th>Area</th><th>Zone</th><th class="num">Subs</th><th class="num">Active</th>
                                <th class="num">Invoiced</th><th class="num">Collected</th><th class="num">Rate</th><th class="num">Due now</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($zones as $row)
                                <tr>
                                    <td>{{ $row['area'] }}</td>
                                    <td class="font-medium">{{ $row['zone'] }}</td>
                                    <td class="num">{{ $row['subscribers'] }}</td>
                                    <td class="num">{{ $row['active'] }}</td>
                                    <td class="num">{{ number_format($row['invoiced'], 2) }}</td>
                                    <td class="num" style="color:var(--bi-success);font-weight:600">{{ number_format($row['collected'], 2) }}</td>
                                    <td class="num">{{ $row['collection_rate'] }}%</td>
                                    <td class="num danger">{{ number_format($row['outstanding'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="isp-bi-empty">No zones with subscribers.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        @if ($activeTab === 'churn')
            @php $churn = $report['churn']; @endphp
            <div class="isp-bi-split isp-bi-split--2">
                <section class="isp-bi-section">
                    <div class="isp-bi-section__head">
                        <div>
                            <h2 class="isp-bi-section__title">Churn by zone</h2>
                            <p class="isp-bi-section__desc">
                                {{ $churn['totals']['suspended'] }} suspended · {{ $churn['totals']['terminated'] }} terminated · {{ $churn['totals']['expired'] }} expired
                            </p>
                        </div>
                    </div>
                    <div class="isp-bi-section__body isp-bi-table-wrap">
                        <table class="isp-bi-table">
                            <thead><tr><th>Area</th><th>Zone</th><th class="num">Total</th></tr></thead>
                            <tbody>
                                @forelse ($churn['by_zone'] as $row)
                                    <tr>
                                        <td>{{ $row['area'] }}</td>
                                        <td>{{ $row['zone'] }}</td>
                                        <td class="num danger">{{ $row['churned'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="isp-bi-empty">No churn in this period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
                <section class="isp-bi-section">
                    <div class="isp-bi-section__head">
                        <div>
                            <h2 class="isp-bi-section__title">Recent churned subscribers</h2>
                        </div>
                    </div>
                    <div class="isp-bi-section__body isp-bi-table-wrap max-h-96 overflow-y-auto">
                        <table class="isp-bi-table">
                            <thead><tr><th>Code</th><th>Name</th><th>Zone</th><th>Status</th></tr></thead>
                            <tbody>
                                @forelse ($churn['recent'] as $row)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $row['customer_code'] }}</td>
                                        <td>{{ $row['name'] }}</td>
                                        <td class="text-xs">{{ $row['zone'] }}</td>
                                        <td class="capitalize">{{ $row['status'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="isp-bi-empty">No records</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        @endif
    </div>
</x-filament-panels::page>
