@php
    $report = $this->report;
    $aging = $report['aging'];
    $rows = $report['rows'];
    $isPrint = request()->boolean('print');
@endphp

<x-filament-panels::page @class(['isp-reports-page', 'isp-reports-page--print' => $isPrint])>
    <div class="space-y-5">
        <section class="isp-reports-hero">
            <div class="isp-reports-hero__main">
                <p class="isp-reports-hero__eyebrow">Reports</p>
                <h2 class="isp-reports-hero__title">Due Report Pro</h2>
                <p class="isp-reports-hero__sub">Aging analysis with bucket totals and detailed invoice lines.</p>
            </div>
        </section>

        <section class="isp-reports-stats isp-reports-stats--aging">
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Current</span>
                <strong>{{ number_format($aging['current'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--warn">
                <span class="isp-reports-stat__label">1–30 days</span>
                <strong>{{ number_format($aging['days_1_30'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--warn">
                <span class="isp-reports-stat__label">31–60 days</span>
                <strong>{{ number_format($aging['days_31_60'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--danger">
                <span class="isp-reports-stat__label">61+ days</span>
                <strong>{{ number_format($aging['days_61_plus'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--primary">
                <span class="isp-reports-stat__label">Total outstanding</span>
                <strong>{{ number_format($aging['total'], 2) }}</strong>
            </div>
        </section>

        <section class="isp-reports-table-card">
            <div class="isp-reports-table-card__head">
                <h3>Detailed due list</h3>
                <span>{{ $report['count'] }} invoices</span>
            </div>
            @if (count($rows) === 0)
                <div class="isp-reports-empty">
                    <p>No outstanding balances found.</p>
                </div>
            @else
                <div class="isp-reports-scroll-table">
                    <table class="isp-reports-data-table">
                        <thead>
                            <tr>
                                <th>Invoice</th>
                                <th>Customer</th>
                                <th>Area</th>
                                <th>Due date</th>
                                <th>Aging</th>
                                <th class="text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr>
                                    <td>{{ $row['invoice_number'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td>{{ $row['area'] }}</td>
                                    <td>{{ $row['due_date'] ?? '—' }}</td>
                                    <td><span class="isp-reports-pill">{{ $row['aging_bucket'] }}</span></td>
                                    <td class="text-right font-semibold">{{ number_format($row['balance_due'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    @if ($isPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-filament-panels::page>
