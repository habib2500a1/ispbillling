@php
    $accent = match ($gateway) {
        'bkash' => ['#e2136e', '#c4105c', 'bKash'],
        'nagad' => ['#f59e0b', '#ea580c', 'Nagad'],
        'rocket' => ['#9333ea', '#7c3aed', 'Rocket'],
        default => ['#0d9488', '#0f766e', $gatewayLabel],
    };
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $accent[2] }} payment</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 1.5rem; }
        .card { max-width: 28rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        h1 { font-size: 1.25rem; margin: 0 0 .5rem; color: {{ $accent[0] }}; }
        .amt { font-size: 1.75rem; font-weight: 700; color: #0f172a; }
        .num { font-size: 1.125rem; font-weight: 600; letter-spacing: .05em; }
        label { display: block; font-size: .875rem; margin-top: 1rem; color: #475569; }
        input[type=text] { width: 100%; padding: .625rem .75rem; border: 1px solid #cbd5e1; border-radius: .5rem; margin-top: .25rem; box-sizing: border-box; }
        button { width: 100%; margin-top: 1.25rem; padding: .75rem; background: linear-gradient(135deg,{{ $accent[0] }},{{ $accent[1] }}); color: #fff; border: 0; border-radius: .5rem; font-weight: 600; cursor: pointer; }
        .steps { font-size: .85rem; color: #475569; margin-top: 1rem; padding-left: 1.1rem; }
        .ref { background: #f1f5f9; padding: .75rem; border-radius: .5rem; margin-top: 1rem; font-size: .875rem; }
        .err { color: #b91c1c; font-size: .875rem; margin-top: .5rem; }
        .pending { background: #fffbeb; border: 1px solid #fcd34d; color: #92400e; padding: .75rem; border-radius: .5rem; margin-top: .75rem; font-size: .875rem; line-height: 1.45; }
        .pending a { color: #b45309; font-weight: 600; }
        .call-btn { display: inline-block; margin-top: .5rem; padding: .5rem .75rem; background: #f59e0b; color: #fff; border-radius: .5rem; text-decoration: none; font-weight: 600; font-size: .875rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $gatewayLabel }} payment</h1>
        <p class="amt">{{ number_format($amount, 2) }} BDT</p>
        @if (($paymentType ?? '') === 'prepay' && ($prepayMonths ?? 0) > 0)
            <p style="font-size:.875rem;color:#64748b;">Advance payment: <strong>{{ $prepayMonths }} month(s)</strong> (includes current due if any)</p>
        @elseif ($invoice)
            <p style="font-size:.875rem;color:#64748b;">Invoice: {{ $invoice->invoice_number }}</p>
        @endif
        <ol class="steps">
            <li>{{ $gatewayLabel }} অ্যাপে <strong>Send Money</strong> খুলুন</li>
            <li>নম্বর: <strong class="num">{{ $merchantNumber }}</strong> ({{ $merchantName }})</li>
            <li>পরিমাণ: <strong>{{ number_format($amount, 2) }} BDT</strong></li>
            <li>পেমেন্টের পর TrxID নিচে লিখে Verify করুন</li>
        </ol>
        <div class="ref">
            <strong>Order ref:</strong> {{ $orderId }}
        </div>
        @if ($instructions)
            <p style="font-size:.875rem;margin-top:.75rem;">{{ $instructions }}</p>
        @endif
        @if (session('mfs_pending') && session('status'))
            <div class="pending" role="status">
                {{ session('status') }}
                @php $tel = \App\Support\PersonalMfsGateway::merchantTelUri($gateway); @endphp
                @if ($tel)
                    <a class="call-btn" href="{{ $tel }}">📞 {{ $gatewayLabel }} নম্বরে কল করুন ({{ $merchantNumber }})</a>
                @endif
            </div>
        @elseif (session('status'))
            <p style="font-size:.875rem;color:#047857;margin-top:.75rem;">{{ session('status') }}</p>
        @endif
        @if (session('danger'))
            <p class="err">{{ session('danger') }}</p>
        @endif
        <form method="post" action="{{ route('mfs.personal.confirm', ['gateway' => $gateway]) }}">
            @csrf
            <input type="hidden" name="order" value="{{ $orderId }}">
            <label for="transaction_id">{{ $gatewayLabel }} Transaction ID (TrxID)</label>
            <input type="text" id="transaction_id" name="transaction_id" value="{{ old('transaction_id') }}" required autocomplete="off" placeholder="TrxID from SMS">
            <button type="submit">Verify payment</button>
        </form>
        <p style="text-align:center;margin-top:1rem;font-size:.8rem;">
            @if (($returnTo ?? '') === 'portal')
                <a href="{{ route('portal.bills.index') }}">← Back to bills</a>
            @elseif (($paymentType ?? '') === 'prepay')
                <a href="{{ route('bill-payment.invoice', ['tab' => 'prepay']) }}">← Back</a>
            @else
                <a href="{{ route('bill-payment.invoice') }}">← Back</a>
            @endif
        </p>
    </div>
</body>
</html>
