<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Collection Balance Statement</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 10px; color: #1a1a1a; line-height: 1.4; margin: 0; }
        .letterhead { border-bottom: 2px solid #1565C0; padding-bottom: 10px; margin-bottom: 14px; }
        .letterhead img { max-height: 48px; max-width: 160px; margin-bottom: 6px; }
        .letterhead h1 { font-size: 16px; margin: 0 0 2px; color: #0D47A1; }
        .letterhead p { margin: 0 0 2px; color: #555; font-size: 9px; }
        .title { font-size: 17px; font-weight: bold; margin: 0 0 4px; color: #0D47A1; }
        .subtitle { color: #666; margin: 0 0 12px; font-size: 10px; }
        .kpi { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .kpi td { border: 1px solid #BBDEFB; padding: 8px; text-align: center; width: 25%; background: #E3F2FD; }
        .kpi .num { font-size: 14px; font-weight: bold; display: block; color: #1565C0; }
        .kpi .cap { font-size: 8px; color: #555; text-transform: uppercase; }
        table.lines { width: 100%; border-collapse: collapse; }
        table.lines th, table.lines td { border: 1px solid #ccc; padding: 6px 7px; text-align: left; }
        table.lines th { background: #1565C0; color: #fff; font-size: 9px; text-transform: uppercase; }
        table.lines td.num { text-align: right; white-space: nowrap; }
        table.lines tr:nth-child(even) td { background: #f8fafc; }
        .due { color: #B45309; font-weight: bold; }
        .footer { margin-top: 16px; font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 8px; }
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

    <p class="title">Collection Balance Statement</p>
    <p class="subtitle">Period {{ $dateFrom }} to {{ $dateTo }} · Generated {{ $generatedAt }}</p>

    <table class="kpi">
        <tr>
            <td><span class="num">{{ number_format($statement['summary']['total_collected'], 0) }}</span><span class="cap">Total collected (BDT)</span></td>
            <td><span class="num">{{ number_format($statement['summary']['total_balance_due'], 0) }}</span><span class="cap">Balance due (BDT)</span></td>
            <td><span class="num">{{ $statement['summary']['total_transactions'] }}</span><span class="cap">Transactions</span></td>
            <td><span class="num">{{ $statement['summary']['staff_count'] }}</span><span class="cap">Staff</span></td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th>Staff</th>
                <th class="num">Collected (BDT)</th>
                <th class="num">Transactions</th>
                <th class="num">Balance due (BDT)</th>
                <th>Last collection</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($statement['staff'] as $row)
                <tr>
                    <td>{{ $row['name'] }}</td>
                    <td class="num">{{ number_format($row['collected_amount'], 2) }}</td>
                    <td class="num">{{ $row['transaction_count'] }}</td>
                    <td class="num {{ $row['balance_due'] > 0 ? 'due' : '' }}">{{ number_format($row['balance_due'], 2) }}</td>
                    <td>{{ $row['last_collection'] ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">No collections in this date range.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p class="footer">
        Balance due reflects current cash in hand (not limited to the selected date range).
        @if ($letterhead['footer'])<br>{{ $letterhead['footer'] }}@endif
    </p>
</body>
</html>
