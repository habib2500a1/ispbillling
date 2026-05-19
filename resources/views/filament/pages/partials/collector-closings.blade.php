<div class="isp-collector-table-wrap">
    <table class="isp-collector-table">
        <thead>
            <tr>
                <th>Date</th>
                <th class="isp-collector-table__num">Collected</th>
                <th class="isp-collector-table__num">Deposited</th>
                <th class="isp-collector-table__num">Variance</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $c)
                <tr>
                    <td>{{ $c->closing_date?->format('d M Y') }}</td>
                    <td class="isp-collector-table__num">{{ number_format($c->collected_total, 0) }}</td>
                    <td class="isp-collector-table__num">{{ number_format($c->deposited_total, 0) }}</td>
                    <td class="isp-collector-table__num {{ abs($c->cash_variance) > 0 ? 'isp-collector-table__due' : '' }}">{{ number_format($c->cash_variance, 2) }}</td>
                    <td><span class="isp-collector-badge">{{ $c->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="5" class="isp-collector-empty">No daily closings yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
