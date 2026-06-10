@php
    $accent = match ($gateway) {
        'bkash' => ['#e2136e', '#c4105c', 'bKash'],
        'nagad' => ['#f59e0b', '#ea580c', 'Nagad'],
        'rocket' => ['#9333ea', '#7c3aed', 'Rocket'],
        default => ['#4f46e5', '#312e81', $gatewayLabel],
    };
@endphp
<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $accent[2] }} — platform bill</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #f8fafc; margin: 0; padding: 1.5rem; }
        .card { max-width: 28rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 1.5rem; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
        h1 { font-size: 1.2rem; margin: 0 0 .5rem; color: {{ $accent[0] }}; }
        .amt { font-size: 1.75rem; font-weight: 700; }
        label { display: block; font-size: .875rem; margin-top: 1rem; color: #475569; }
        input[type=text] { width: 100%; padding: .625rem .75rem; border: 1px solid #cbd5e1; border-radius: .5rem; margin-top: .25rem; box-sizing: border-box; }
        button[type=submit] { width: 100%; margin-top: 1.25rem; padding: .75rem; background: linear-gradient(135deg,{{ $accent[0] }},{{ $accent[1] }}); color: #fff; border: 0; border-radius: .5rem; font-weight: 600; cursor: pointer; }
        .copy-btn { display: inline-block; width: auto; margin: 0 0 0 .35rem; padding: .2rem .5rem; font-size: .75rem; font-weight: 600; border: 1px solid #cbd5e1; border-radius: .35rem; background: #f8fafc; color: #334155; cursor: pointer; vertical-align: middle; }
        .copy-btn:hover { background: #e2e8f0; }
        .steps { font-size: .85rem; color: #475569; margin-top: 1rem; padding-left: 1.1rem; }
    </style>
</head>
<body>
    <div class="card">
        <h1>{{ $gatewayLabel }} — Platform bill</h1>
        <p class="amt">{{ number_format($amount, 2) }} BDT</p>
        <p style="font-size:.875rem;color:#64748b;">{{ $invoice->invoice_number }} · {{ $tenant?->name }}</p>
        <ol class="steps">
            <li>{{ $gatewayLabel }} এ Send Money করুন</li>
            <li>নম্বর: <strong>{{ $merchantNumber }}</strong>
                <button type="button" class="copy-btn" data-copy-text="{{ $merchantNumber }}" title="নম্বর কপি করুন">📋 Copy</button>
                ({{ $merchantName }})</li>
            <li>TrxID নিচে দিন</li>
        </ol>
        <form method="post" action="{{ route('platform-invoice.confirm-mfs', $token) }}">
            @csrf
            <input type="hidden" name="order" value="{{ $orderId }}">
            <input type="hidden" name="gateway" value="{{ $gateway }}">
            <label>Transaction ID (TrxID)</label>
            <input type="text" name="transaction_id" required maxlength="64" autocomplete="off">
            <button type="submit">Confirm payment</button>
        </form>
    </div>
    <script>
        document.querySelectorAll('[data-copy-text]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var text = btn.getAttribute('data-copy-text');
                if (!text) return;
                var done = function () {
                    var prev = btn.textContent;
                    btn.textContent = '✓ Copied';
                    setTimeout(function () { btn.textContent = prev; }, 1500);
                };
                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(text).then(done).catch(function () {
                        window.prompt('নম্বর কপি করুন:', text);
                    });
                } else {
                    window.prompt('নম্বর কপি করুন:', text);
                }
            });
        });
    </script>
</body>
</html>
