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
                <h2 class="isp-reports-hero__title">Package-wise Report</h2>
                <p class="isp-reports-hero__sub">Subscriber counts and estimated MRR per internet package.</p>
            </div>
        </section>

        <section class="isp-reports-stats">
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Packages</span>
                <strong>{{ number_format($stats['packages']) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--primary">
                <span class="isp-reports-stat__label">Active subs</span>
                <strong>{{ number_format($stats['active']) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Est. MRR</span>
                <strong>{{ number_format($stats['mrr'], 2) }}</strong>
            </div>
        </section>

        <section class="isp-reports-table-card">
            <div class="isp-reports-table-card__head">
                <h3>Package breakdown</h3>
                <span>{{ count($rows) }} packages</span>
            </div>
            <div class="isp-reports-scroll-table">
                <table class="isp-reports-data-table">
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Speed</th>
                            <th class="text-right">Price</th>
                            <th class="text-right">Subscribers</th>
                            <th class="text-right">Active</th>
                            <th class="text-right">Est. MRR</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['package'] }}</td>
                                <td>{{ $row['speed'] }}</td>
                                <td class="text-right">{{ number_format($row['price'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['subscribers']) }}</td>
                                <td class="text-right">{{ number_format($row['active']) }}</td>
                                <td class="text-right font-semibold">{{ number_format($row['est_mrr'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="isp-reports-empty-cell">No packages configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if ($isPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</x-filament-panels::page>
