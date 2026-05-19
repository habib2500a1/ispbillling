@php
    $report = $this->report;
    $details = $this->detailRows;
@endphp

<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero isp-reseller-hero--compact">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Report</h2>
                <p class="isp-reseller-hero__sub">Commission earned by partner for the selected period.</p>
            </div>
        </section>

        <section class="isp-reseller-form-card isp-reseller-form-card--inline">
            {{ $this->form }}
        </section>

        <section class="isp-reseller-stats">
            <div class="isp-reseller-stat isp-reseller-stat--primary">
                <span class="isp-reseller-stat__label">Total commission</span>
                <strong>{{ number_format($report['total_commission'], 2) }}</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Pending</span>
                <strong>{{ number_format($report['pending'], 2) }}</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Paid</span>
                <strong>{{ number_format($report['paid'], 2) }}</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Partners</span>
                <strong>{{ number_format($report['partners']) }}</strong>
            </div>
        </section>

        <section class="isp-reseller-table-card">
            <div class="isp-reseller-table-card__head">
                <h3>By reseller</h3>
            </div>
            <div class="isp-reseller-scroll-table">
                <table class="isp-reseller-data-table">
                    <thead>
                        <tr>
                            <th>Reseller</th>
                            <th class="text-right">Transactions</th>
                            <th class="text-right">Commission</th>
                            <th class="text-right">Pending</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['rows'] as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['reseller'] }}</td>
                                <td class="text-right">{{ number_format($row['transactions']) }}</td>
                                <td class="text-right">{{ number_format($row['commission'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['pending'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="isp-reseller-empty-cell">No commission in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if (count($details) > 0)
            <section class="isp-reseller-table-card">
                <div class="isp-reseller-table-card__head">
                    <h3>Recent commission lines</h3>
                    <span>{{ count($details) }} rows</span>
                </div>
                <div class="isp-reseller-scroll-table">
                    <table class="isp-reseller-data-table">
                        <thead>
                            <tr>
                                <th>Earned</th>
                                <th>Reseller</th>
                                <th>Customer</th>
                                <th class="text-right">Gross</th>
                                <th class="text-right">Commission</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($details as $row)
                                <tr>
                                    <td>{{ $row['earned_at'] }}</td>
                                    <td>{{ $row['reseller'] }}</td>
                                    <td>{{ $row['customer'] }}</td>
                                    <td class="text-right">{{ number_format($row['gross'], 2) }}</td>
                                    <td class="text-right font-semibold">{{ number_format($row['commission'], 2) }}</td>
                                    <td><span class="isp-reseller-pill">{{ $row['status'] }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
