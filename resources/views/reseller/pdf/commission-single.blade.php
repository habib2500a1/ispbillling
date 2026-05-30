<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Commission #{{ $commission->id }}</title>
    <style>
        body { font-family: dejavusans, sans-serif; font-size: 11px; color: #1a1a1a; }
        .letterhead { border-bottom: 2px solid #333; padding-bottom: 10px; margin-bottom: 14px; }
        .letterhead img { max-height: 48px; max-width: 160px; margin-bottom: 6px; }
        .letterhead h1 { font-size: 16px; margin: 0 0 2px; }
        .letterhead p { margin: 0 0 2px; color: #555; font-size: 9px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ccc; padding: 7px 8px; text-align: left; }
        th { background: #f3f4f6; width: 35%; }
    </style>
</head>
<body>
    <div class="letterhead">
        @if ($letterhead['showLogo'] && $letterhead['logoPath'])
            <img src="{{ $letterhead['logoPath'] }}" alt="">
        @endif
        <h1>{{ $letterhead['name'] }}</h1>
        @if ($letterhead['tagline'])<p>{{ $letterhead['tagline'] }}</p>@endif
    </div>

    <h2 style="margin:0 0 8px;font-size:15px;">Commission receipt</h2>
    <p style="margin:0 0 12px;color:#666;">Partner: {{ $reseller->name }} ({{ $reseller->code }})</p>

    <table>
        <tr><th>Earned at</th><td>{{ $commission->earned_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
        <tr><th>Subscriber</th><td>{{ $commission->customer?->name ?? '—' }} ({{ $commission->customer?->customer_code ?? '—' }})</td></tr>
        <tr><th>Gross payment</th><td>{{ number_format((float) $commission->gross_amount, 2) }} BDT</td></tr>
        <tr><th>Commission</th><td><strong>{{ number_format((float) $commission->commission_amount, 2) }} BDT</strong></td></tr>
        <tr><th>Status</th><td>{{ ucfirst($commission->status) }}</td></tr>
        <tr><th>Payment method</th><td>{{ $commission->payment?->method ?? '—' }}</td></tr>
        <tr><th>Payment date</th><td>{{ $commission->payment?->paid_at?->format('Y-m-d H:i') ?? '—' }}</td></tr>
        @if ($commission->payment?->receipt_number)
            <tr><th>Receipt</th><td>{{ $commission->payment->receipt_number }}</td></tr>
        @endif
        @if ($commission->paid_at)
            <tr><th>Paid to wallet</th><td>{{ $commission->paid_at->format('Y-m-d H:i') }}</td></tr>
        @endif
    </table>
</body>
</html>
