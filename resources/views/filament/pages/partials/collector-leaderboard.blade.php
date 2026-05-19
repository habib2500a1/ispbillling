<div class="isp-collector-table-wrap">
    <table class="isp-collector-table">
        <thead>
            <tr>
                <th>Collector</th>
                <th class="isp-collector-table__num">Collected</th>
                <th class="isp-collector-table__num">Deposited</th>
                <th class="isp-collector-table__num">Due</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $row)
                <tr>
                    <td>
                        {{ $row['name'] }}
                        @if ($row['branch'] ?? null)
                            <span class="isp-collector-muted">{{ $row['branch'] }}</span>
                        @endif
                    </td>
                    <td class="isp-collector-table__num">{{ number_format($row['total_collected'], 0) }}</td>
                    <td class="isp-collector-table__num">{{ number_format($row['total_settled'], 0) }}</td>
                    <td class="isp-collector-table__num isp-collector-table__due">{{ number_format($row['outstanding'], 0) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
