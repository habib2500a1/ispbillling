<div class="isp-collector-table-wrap">
    <table class="isp-collector-table">
        <thead>
            <tr>
                <th>#</th>
                <th class="isp-collector-table__num">Amount</th>
                <th>Status</th>
                <th>Approved</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $s)
                <tr>
                    <td class="isp-collector-mono">{{ $s->settlement_number }}</td>
                    <td class="isp-collector-table__num">{{ number_format($s->amount, 2) }}</td>
                    <td><span class="isp-collector-badge">{{ $s->status }}</span></td>
                    <td>{{ $s->approver?->name ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="isp-collector-empty">No settlements yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
