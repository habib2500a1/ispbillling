<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Platform bill — {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: linear-gradient(160deg,#eef2ff,#f8fafc); margin: 0; padding: 1.5rem; }
        .card { max-width: 32rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 8px 32px rgba(79,70,229,.12); }
        h1 { font-size: 1.35rem; margin: 0 0 .25rem; color: #312e81; }
        .sub { color: #64748b; font-size: .9rem; margin-bottom: 1rem; }
        .amt { font-size: 2rem; font-weight: 800; color: #0f172a; }
        dl { display: grid; gap: .5rem; margin: 1rem 0; font-size: .9rem; }
        dl div { display: flex; justify-content: space-between; gap: 1rem; }
        dt { color: #64748b; }
        dd { margin: 0; font-weight: 600; text-align: right; }
        .methods { display: grid; gap: .65rem; margin-top: 1.25rem; }
        .btn { display: block; text-align: center; padding: .8rem 1rem; border-radius: .65rem; font-weight: 700; text-decoration: none; border: 0; cursor: pointer; font-size: .95rem; }
        .btn-pp { background: linear-gradient(135deg,#4f46e5,#0ea5e9); color: #fff; }
        .btn-mfs { background: #f8fafc; color: #0f172a; border: 1px solid #e2e8f0; }
        .status { padding: .75rem; border-radius: .5rem; margin-bottom: 1rem; font-size: .875rem; }
        .status-ok { background: #ecfdf5; color: #065f46; }
        .status-err { background: #fef2f2; color: #991b1b; }
        .badge { display: inline-block; padding: .2rem .55rem; border-radius: 999px; font-size: .75rem; font-weight: 700; background: #fef3c7; color: #92400e; }
    </style>
</head>
<body>
    <div class="card">
        @if (session('status'))
            <div class="status status-ok">{{ session('status') }}</div>
        @endif
        @if (session('danger'))
            <div class="status status-err">{{ session('danger') }}</div>
        @endif

        <h1>ISP Platform Bill</h1>
        <p class="sub">{{ $tenant?->name }} · {{ $invoice->plan_name }}</p>
        <p class="amt">{{ number_format($invoice->amount, 0) }} BDT</p>
        <span class="badge">{{ ucfirst($invoice->status) }}</span>

        <dl>
            <div><dt>Invoice</dt><dd>{{ $invoice->invoice_number }}</dd></div>
            <div><dt>Period</dt><dd>{{ $invoice->billing_period }}</dd></div>
            <div><dt>Customers</dt><dd>{{ $invoice->customer_count }}{{ $invoice->max_customers ? ' / '.$invoice->max_customers : '' }}</dd></div>
            <div><dt>Due date</dt><dd>{{ $invoice->due_date?->format('d M Y') }}</dd></div>
        </dl>

        <div class="methods">
            @if ($piprapayEnabled)
                <form method="post" action="{{ route('platform-invoice.piprapay', $invoice->payment_token) }}">
                    @csrf
                    <button type="submit" class="btn btn-pp">Pay online (PipraPay)</button>
                </form>
            @endif
            @if ($bkashEnabled)
                <form method="post" action="{{ route('platform-invoice.start-mfs', $invoice->payment_token) }}">
                    @csrf
                    <input type="hidden" name="gateway" value="bkash">
                    <button type="submit" class="btn btn-mfs">bKash Send Money + TrxID</button>
                </form>
            @endif
            @if ($nagadEnabled)
                <form method="post" action="{{ route('platform-invoice.start-mfs', $invoice->payment_token) }}">
                    @csrf
                    <input type="hidden" name="gateway" value="nagad">
                    <button type="submit" class="btn btn-mfs">Nagad Send Money + TrxID</button>
                </form>
            @endif
            @if ($rocketEnabled)
                <form method="post" action="{{ route('platform-invoice.start-mfs', $invoice->payment_token) }}">
                    @csrf
                    <input type="hidden" name="gateway" value="rocket">
                    <button type="submit" class="btn btn-mfs">Rocket + TrxID</button>
                </form>
            @endif
        </div>

        <p style="margin-top:1.25rem;font-size:.78rem;color:#94a3b8;text-align:center;">
            One bill per month · Auto-generated on your subscription bill day
        </p>
    </div>
</body>
</html>
