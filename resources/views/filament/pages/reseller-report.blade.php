@php
    $report = $this->report;
    $monthly = $this->monthlyRows;
    $statusRows = $this->statusRows;
    $details = $this->detailRows;

    $statusClass = fn (string $status): string => match ($status) {
        'pending' => 'isp-reseller-pill isp-reseller-pill--warning',
        'paid' => 'isp-reseller-pill isp-reseller-pill--success',
        'cancelled' => 'isp-reseller-pill isp-reseller-pill--danger',
        default => 'isp-reseller-pill',
    };
@endphp

<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero isp-reseller-hero--compact">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Commission report</h2>
                <p class="isp-reseller-hero__sub">Monthly totals, status breakdown, and line-by-line ledger for settlement.</p>
            </div>
        </section>

        <section class="isp-reseller-form-card isp-reseller-form-card--inline">
            {{ $this->form }}
        </section>

        <section class="isp-reseller-stats">
            <div class="isp-reseller-stat isp-reseller-stat--primary">
                <span class="isp-reseller-stat__label">Total commission</span>
                <strong>{{ number_format($report['total_commission'], 2) }} ৳</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Pending</span>
                <strong class="text-amber-600">{{ number_format($report['pending'], 2) }} ৳</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Paid</span>
                <strong class="text-emerald-600">{{ number_format($report['paid'], 2) }} ৳</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Cancelled</span>
                <strong>{{ number_format($report['cancelled'] ?? 0, 2) }} ৳</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Partners</span>
                <strong>{{ number_format($report['partners']) }}</strong>
            </div>
        </section>

        <div class="grid gap-5 lg:grid-cols-2">
            <section class="isp-reseller-table-card">
                <div class="isp-reseller-table-card__head">
                    <h3>By month</h3>
                    <span>{{ count($monthly) }} month(s)</span>
                </div>
                <div class="isp-reseller-scroll-table">
                    <table class="isp-reseller-data-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th class="text-right">Lines</th>
                                <th class="text-right">Total</th>
                                <th class="text-right">Pending</th>
                                <th class="text-right">Paid</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($monthly as $row)
                                <tr>
                                    <td class="font-semibold">{{ $row['label'] }}</td>
                                    <td class="text-right">{{ number_format($row['count']) }}</td>
                                    <td class="text-right font-semibold">{{ number_format($row['total'], 2) }}</td>
                                    <td class="text-right text-amber-700">{{ number_format($row['pending'], 2) }}</td>
                                    <td class="text-right text-emerald-700">{{ number_format($row['paid'], 2) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="isp-reseller-empty-cell">No commission in this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="isp-reseller-table-card">
                <div class="isp-reseller-table-card__head">
                    <h3>By status</h3>
                    <span>Settlement snapshot</span>
                </div>
                <div class="isp-reseller-scroll-table">
                    <table class="isp-reseller-data-table">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th class="text-right">Entries</th>
                                <th class="text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($statusRows as $row)
                                <tr>
                                    <td><span class="{{ $statusClass($row['status']) }}">{{ $row['label'] }}</span></td>
                                    <td class="text-right">{{ number_format($row['count']) }}</td>
                                    <td class="text-right font-semibold">{{ number_format($row['amount'], 2) }} ৳</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="isp-reseller-empty-cell">No data.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <section class="isp-reseller-table-card">
            <div class="isp-reseller-table-card__head">
                <h3>By reseller</h3>
            </div>
            <div class="isp-reseller-scroll-table">
                <table class="isp-reseller-data-table">
                    <thead>
                        <tr>
                            <th>Reseller</th>
                            <th class="text-right">Lines</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Pending</th>
                            <th class="text-right">Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($report['rows'] as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['reseller'] }}</td>
                                <td class="text-right">{{ number_format($row['transactions']) }}</td>
                                <td class="text-right">{{ number_format($row['commission'], 2) }}</td>
                                <td class="text-right text-amber-700">{{ number_format($row['pending'], 2) }}</td>
                                <td class="text-right text-emerald-700">{{ number_format($row['paid'] ?? 0, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="isp-reseller-empty-cell">No commission in this period.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        @if (count($details) > 0)
            <section class="isp-reseller-table-card">
                <div class="isp-reseller-table-card__head">
                    <h3>Commission ledger (detail)</h3>
                    <span>{{ count($details) }} rows · max 500</span>
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
                                    <td><span class="{{ $statusClass($row['status']) }}">{{ ucfirst($row['status']) }}</span></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
