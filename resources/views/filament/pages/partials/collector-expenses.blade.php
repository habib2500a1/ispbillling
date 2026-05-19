<div class="isp-collector-table-wrap">
    <table class="isp-collector-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th class="isp-collector-table__num">Amount</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $e)
                <tr>
                    <td>{{ $e->expense_date?->format('d M Y') }}</td>
                    <td>{{ $e->category?->name ?? '—' }}</td>
                    <td class="isp-collector-table__num">{{ number_format($e->amount, 2) }}</td>
                    <td><span class="isp-collector-badge">{{ $e->status }}</span></td>
                </tr>
            @empty
                <tr><td colspan="4" class="isp-collector-empty">No expenses yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
