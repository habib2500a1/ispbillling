@php
    $rows = $this->rows;
    $totals = $this->totals;
@endphp

<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero isp-reseller-hero--compact">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Collection performance</h2>
                <p class="isp-reseller-hero__sub">Customer due vs collections vs HQ admin receivable by partner.</p>
            </div>
        </section>

        <section class="isp-reseller-form-card isp-reseller-form-card--inline">
            {{ $this->form }}
        </section>

        <section class="isp-reseller-stats">
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Partners</span>
                <strong>{{ $totals['partners'] }}</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Customer due</span>
                <strong class="text-rose-600">{{ number_format($totals['customer_due'], 0) }} ৳</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Admin receivable</span>
                <strong>{{ number_format($totals['admin_receivable'], 0) }} ৳</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Collected (period)</span>
                <strong class="text-emerald-600">{{ number_format($totals['collected'], 0) }} ৳</strong>
            </div>
            <div class="isp-reseller-stat">
                <span class="isp-reseller-stat__label">Avg collection rate</span>
                <strong>{{ number_format($totals['avg_collection_rate'], 1) }}%</strong>
            </div>
        </section>

        <section class="isp-reseller-table-card">
            <div class="isp-reseller-table-card__head">
                <h3>By partner</h3>
            </div>
            <div class="isp-reseller-scroll-table">
                <table class="isp-reseller-data-table">
                    <thead>
                        <tr>
                            <th>Partner</th>
                            <th class="text-right">Subs</th>
                            <th class="text-right">Customer due</th>
                            <th class="text-right">Collected</th>
                            <th class="text-right">Rate</th>
                            <th class="text-right">HQ due</th>
                            <th class="text-right">Credit %</th>
                            <th class="text-right">Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td>
                                    <a href="{{ \App\Filament\Resources\ResellerResource::getUrl('view', ['record' => $row['id']]) }}" class="font-semibold text-primary-600 hover:underline">
                                        {{ $row['name'] }}
                                    </a>
                                    <span class="block text-xs text-gray-500">{{ $row['code'] }}</span>
                                </td>
                                <td class="text-right">{{ $row['customers'] }} <span class="text-xs text-gray-500">({{ $row['active_customers'] }} on)</span></td>
                                <td class="text-right text-rose-700">{{ number_format($row['customer_due'], 0) }}</td>
                                <td class="text-right text-emerald-700">{{ number_format($row['collected'], 0) }}</td>
                                <td class="text-right">{{ number_format($row['collection_rate'], 1) }}%</td>
                                <td class="text-right">{{ number_format($row['admin_receivable'], 0) }}</td>
                                <td class="text-right">{{ $row['credit_util'] }}%</td>
                                <td class="text-right">{{ number_format($row['risk_score'], 0) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="isp-reseller-empty-cell">No resellers found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
