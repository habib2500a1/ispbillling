<div class="isp-collector-table-wrap">
    <table class="isp-collector-table">
        <thead>
            <tr>
                <th>Date</th>
                <th>Customer</th>
                <th class="isp-collector-table__num">Amount</th>
                <th class="isp-collector-table__num">Due</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->collected_at?->format('d M H:i') }}</td>
                    <td>{{ $row->customer?->name }} <span class="isp-collector-mono">{{ $row->customer?->customer_code }}</span></td>
                    <td class="isp-collector-table__num">{{ number_format($row->amount, 2) }}</td>
                    <td class="isp-collector-table__num isp-collector-table__due">{{ number_format($row->outstandingAmount(), 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="isp-collector-empty">No open collections.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
