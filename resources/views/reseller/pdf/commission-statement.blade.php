<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Commission statement</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.4; margin: 0; }
        .letterhead { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 14px; }
        .letterhead img { max-height: 48px; max-width: 160px; margin-bottom: 6px; }
        .letterhead h1 { font-size: 16px; margin: 0 0 2px; }
        .letterhead p { margin: 0 0 2px; color: #555; font-size: 9px; }
        .title { font-size: 17px; font-weight: bold; margin: 0 0 4px; }
        .subtitle { color: #666; margin: 0 0 12px; font-size: 10px; }
        .meta { width: 100%; margin-bottom: 14px; border-collapse: collapse; }
        .meta td { vertical-align: top; width: 50%; padding: 0 10px 0 0; }
        .label { font-size: 8px; text-transform: uppercase; color: #666; letter-spacing: 0.05em; }
        .value { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .kpi { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .kpi td { border: 1px solid #ccc; padding: 8px; text-align: center; width: 25%; }
        .kpi .num { font-size: 14px; font-weight: bold; display: block; }
        .kpi .cap { font-size: 8px; color: #666; text-transform: uppercase; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        table.lines th { background: #f3f4f6; font-size: 9px; text-transform: uppercase; }
        table.lines td.num { text-align: right; white-space: nowrap; }
        .footer { margin-top: 16px; font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
        .muted { color: #666; }
    </style>
</head>
<body>
    <div class="letterhead">
        @if ($letterhead['showLogo'] && $letterhead['logoPath'])
            <img src="{{ $letterhead['logoPath'] }}" alt="">
        @endif
        <h1>{{ $letterhead['name'] }}</h1>
        @if ($letterhead['tagline'])<p>{{ $letterhead['tagline'] }}</p>@endif
        @if ($letterhead['address'])<p>{{ $letterhead['address'] }}</p>@endif
        @if ($letterhead['phone'] || $letterhead['email'])
            <p>{{ trim($letterhead['phone'].($letterhead['phone'] && $letterhead['email'] ? ' · ' : '').$letterhead['email']) }}</p>
        @endif
    </div>

    <p class="title">Partner commission statement</p>
    <p class="subtitle">Period {{ $from }} to {{ $to }}@if($statusFilter) · Status: {{ ucfirst($statusFilter) }}@endif</p>

    <table class="meta">
        <tr>
            <td>
                <div class="label">Partner</div>
                <div class="value">{{ $reseller->name }}</div>
                <div class="muted">{{ $reseller->code }} · {{ $reseller->franchiseTypeLabel() }}</div>
            </td>
            <td>
                <div class="label">Commission rule</div>
                <div class="value">{{ $reseller->commissionLabel() }}</div>
                @if ($reseller->phone)
                    <div class="muted">Phone: {{ $reseller->phone }}</div>
                @endif
            </td>
        </tr>
    </table>

    <table class="kpi">
        <tr>
            <td><span class="num">{{ number_format($filteredTotal, 2) }}</span><span class="cap">Commission (shown)</span></td>
            <td><span class="num">{{ number_format($filteredPending, 2) }}</span><span class="cap">Pending</span></td>
            <td><span class="num">{{ number_format($filteredPaid, 2) }}</span><span class="cap">Paid</span></td>
            <td><span class="num">{{ $rows->count() }}</span><span class="cap">Lines</span></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Earned</th>
                <th>Subscriber</th>
                <th>Code</th>
                <th>Gross BDT</th>
                <th>Commission BDT</th>
                <th>Method</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td>{{ $row->earned_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>{{ $row->customer?->name ?? '—' }}</td>
                    <td>{{ $row->customer?->customer_code ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $row->gross_amount, 2) }}</td>
                    <td class="num">{{ number_format((float) $row->commission_amount, 2) }}</td>
                    <td>{{ $row->payment?->method ?? '—' }}</td>
                    <td>{{ ucfirst($row->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No commission records in this period.</td></tr>
            @endforelse
        </tbody>
        @if ($rows->isNotEmpty())
            <tfoot>
                <tr>
                    <td colspan="4" class="num"><strong>Total</strong></td>
                    <td class="num"><strong>{{ number_format($filteredTotal, 2) }}</strong></td>
                    <td colspan="2"></td>
                </tr>
            </tfoot>
        @endif
    </table>

    <div class="footer">
        Generated {{ $generatedAt->format('Y-m-d H:i') }} · Period summary total {{ number_format($summary['total_commission'], 2) }} BDT
        (pending {{ number_format($summary['pending'], 2) }}, paid {{ number_format($summary['paid'], 2) }}).
        @if ($letterhead['footer'])<br>{{ $letterhead['footer'] }}@endif
    </div>
</body>
</html>
