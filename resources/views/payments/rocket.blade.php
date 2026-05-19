<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rocket payment</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 1.5rem; }
        .card { max-width: 28rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; color: #7c2d12; }
        .amt { font-size: 1.75rem; font-weight: 700; color: #0f172a; }
        .num { font-size: 1.125rem; font-weight: 600; letter-spacing: .05em; }
        label { display: block; font-size: .875rem; margin-top: 1rem; color: #475569; }
        input[type=text] { width: 100%; padding: .625rem .75rem; border: 1px solid #cbd5e1; border-radius: .5rem; margin-top: .25rem; box-sizing: border-box; }
        button { width: 100%; margin-top: 1.25rem; padding: .75rem; background: linear-gradient(135deg,#9333ea,#7c3aed); color: #fff; border: 0; border-radius: .5rem; font-weight: 600; cursor: pointer; }
        .ref { background: #fef3c7; padding: .75rem; border-radius: .5rem; margin-top: 1rem; font-size: .875rem; }
        .err { color: #b91c1c; font-size: .875rem; margin-top: .5rem; }
        .ok { color: #15803d; font-size: .875rem; margin-top: .5rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Rocket payment</h1>
        <p class="amt">{{ number_format($amount, 2) }} BDT</p>
        @if ($invoice)
            <p style="font-size:.875rem;color:#64748b;">Invoice: {{ $invoice->invoice_number }}</p>
        @endif
        <p style="margin-top:1rem;font-size:.9rem;">Send money from your Rocket app to:</p>
        <p class="num">{{ $merchantNumber }}</p>
        <p style="font-size:.875rem;color:#64748b;">{{ $merchantName }}</p>
        <div class="ref">
            <strong>Reference / note:</strong> {{ $orderId }}<br>
            <span style="font-size:.8rem;">Use this reference when paying so we can match your payment.</span>
        </div>
        @if ($instructions)
            <p style="font-size:.875rem;margin-top:.75rem;">{{ $instructions }}</p>
        @endif
        @if (session('danger'))
            <p class="err">{{ session('danger') }}</p>
        @endif
        <form method="post" action="{{ route('rocket.confirm') }}">
            @csrf
            <input type="hidden" name="order" value="{{ $orderId }}">
            <label for="transaction_id">Rocket transaction ID (TrxID)</label>
            <input type="text" id="transaction_id" name="transaction_id" value="{{ old('transaction_id') }}" required autocomplete="off" placeholder="e.g. RKT12345678">
            <button type="submit">Confirm payment</button>
        </form>
        <p style="text-align:center;margin-top:1rem;font-size:.8rem;">
            <a href="{{ route('bill-payment.invoice') }}">← Back</a>
        </p>
    </div>
</body>
</html>
