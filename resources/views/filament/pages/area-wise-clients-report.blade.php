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
                <h2 class="isp-reports-hero__title">Area-wise Clients</h2>
                <p class="isp-reports-hero__sub">Active subscribers, collections (MTD), and outstanding by area.</p>
            </div>
        </section>

        <section class="isp-reports-stats">
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Areas</span>
                <strong>{{ number_format($stats['areas']) }}</strong>
            </div>
            <div class="isp-reports-stat isp-reports-stat--primary">
                <span class="isp-reports-stat__label">Active clients</span>
                <strong>{{ number_format($stats['active']) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Collected (MTD)</span>
                <strong>{{ number_format($stats['collected'], 2) }}</strong>
            </div>
            <div class="isp-reports-stat">
                <span class="isp-reports-stat__label">Outstanding</span>
                <strong>{{ number_format($stats['due'], 2) }}</strong>
            </div>
        </section>

        <section class="isp-reports-table-card">
            <div class="isp-reports-table-card__head">
                <h3>By area</h3>
                <span>{{ count($rows) }} areas</span>
            </div>
            <div class="isp-reports-scroll-table">
                <table class="isp-reports-data-table">
                    <thead>
                        <tr>
                            <th>Area</th>
                            <th>Code</th>
                            <th class="text-right">Active</th>
                            <th class="text-right">Total</th>
                            <th class="text-right">Collected MTD</th>
                            <th class="text-right">Outstanding</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            <tr>
                                <td class="font-semibold">{{ $row['area'] }}</td>
                                <td>{{ $row['code'] }}</td>
                                <td class="text-right">{{ number_format($row['active']) }}</td>
                                <td class="text-right">{{ number_format($row['total_customers']) }}</td>
                                <td class="text-right">{{ number_format($row['collected_mtd'], 2) }}</td>
                                <td class="text-right">{{ number_format($row['outstanding'], 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="isp-reports-empty-cell">No area data yet.</td>
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
