@php
    $report = $this->report;
    $aging = $report['aging'];
    $rows = $report['rows'];
    $isPrint = request()->boolean('print');
@endphp

<x-filament-panels::page @class(['isp-bi-page--print' => $isPrint])>
    <div class="isp-bi-page">
        @unless ($isPrint)
            <x-isp.reports.page-header
                eyebrow="Collection analytics"
                title="Due report pro"
                subtitle="Aging analysis with bucket totals and detailed invoice lines."
                score-label="Total outstanding"
                :score-value="number_format($aging['total'], 0).' BDT'"
            />

            <div class="isp-bi-toolbar">
                <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="isp-bi-back">← Intelligence center</a>
                <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'due']) }}" class="isp-bi-back">Analytics due tab →</a>
            </div>
        @else
            <header class="mb-4">
                <h1 class="text-xl font-bold">Due Report Pro</h1>
                <p class="text-sm text-gray-600">{{ $report['count'] }} invoices · {{ number_format($aging['total'], 2) }} BDT outstanding</p>
            </header>
        @endunless

        <div class="isp-bi-kpi-grid isp-bi-kpi-grid--4">
            <x-isp.reports.kpi-card label="Current" :value="number_format($aging['current'], 2).' BDT'" tone="sky" />
            <x-isp.reports.kpi-card label="1–30 days" :value="number_format($aging['days_1_30'], 2).' BDT'" tone="amber" />
            <x-isp.reports.kpi-card label="31–60 days" :value="number_format($aging['days_31_60'], 2).' BDT'" tone="amber" />
            <x-isp.reports.kpi-card label="61+ days" :value="number_format($aging['days_61_plus'], 2).' BDT'" tone="rose" />
        </div>

        <section class="isp-bi-section">
            <div class="isp-bi-section__head">
                <div>
                    <h2 class="isp-bi-section__title">Detailed due list</h2>
                    <p class="isp-bi-section__desc">{{ $report['count'] }} invoices</p>
                </div>
            </div>
            <div class="isp-bi-section__body">
                @if (count($rows) === 0)
                    <p class="isp-bi-empty">No outstanding balances found.</p>
                @else
                    <div class="isp-bi-table-wrap">
                        <table class="isp-bi-table">
                            <thead>
                                <tr>
                                    <th>Invoice</th>
                                    <th>Customer</th>
                                    <th>Area</th>
                                    <th>Due date</th>
                                    <th>Aging</th>
                                    <th class="num">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($rows as $row)
                                    <tr>
                                        <td class="font-mono text-xs">{{ $row['invoice_number'] }}</td>
                                        <td>{{ $row['customer'] }}</td>
                                        <td>{{ $row['area'] }}</td>
                                        <td>{{ $row['due_date'] ?? '—' }}</td>
                                        <td><span class="isp-bi-chip">{{ $row['aging_bucket'] }}</span></td>
                                        <td class="num font-semibold">{{ number_format($row['balance_due'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>

    @if ($isPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-filament-panels::page>
