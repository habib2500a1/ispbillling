@php
    $stats = $this->stats;
    $rows = $this->rows;
    $isPrint = request()->boolean('print');
@endphp

<x-filament-panels::page @class(['isp-reports-page', 'isp-reports-page--print' => $isPrint])>
    <div class="space-y-5">
        <section class="isp-reports-hero">
            <div class="isp-reports-hero__main">
                <p class="isp-reports-hero__eyebrow">Reports</p>
                <h2 class="isp-reports-hero__title">Due Report</h2>
                <p class="isp-reports-hero__sub">Open invoices with outstanding balance, sorted by due date.</p>
            </div>
        </section>

        <section class="isp-reports-stats">
            <div class="isp-reports-stat isp-reports-stat--primary">
                <span class="isp-reports-stat__label">Total due</span>
                <strong>{{ number_format($stats['total_due'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Invoices</span>
                <strong>{{ number_format($stats['invoices']) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Overdue</span>
                <strong>{{ number_format($stats['overdue_count']) }}</strong>
            </div>
        </section>

        <section class="isp-reports-table-card">
            <div class="isp-reports-table-card__head">
                <h3>Outstanding invoices</h3>
                <span>{{ count($rows) }} rows</span>
            </div>
            @if (count($rows) === 0)
                <div class="isp-reports-empty">
                    <p>No outstanding invoices. All clients are up to date.</p>
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
                                <th>Days overdue</th>
                                <th class="text-right">Balance</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr @class(['isp-reports-row--overdue' => ($row['days_overdue'] ?? 0) > 0])>
                                    <td>{{ $row['invoice_number'] }}</td>
                                    <td>
                                        <span class="font-semibold">{{ $row['customer'] }}</span>
                                        <span class="block text-xs text-gray-500">{{ $row['customer_code'] }}</span>
                                    </td>
                                    <td>{{ $row['area'] }}</td>
                                    <td>{{ $row['due_date'] ?? '—' }}</td>
                                    <td>{{ $row['days_overdue'] }}</td>
                                    <td class="text-right font-semibold">{{ number_format($row['balance_due'], 2) }}</td>
                                    <td><span class="isp-reports-pill">{{ $row['status'] }}</span></td>
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
