<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Paid — {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #ecfdf5; margin: 0; padding: 2rem; }
        .card { max-width: 28rem; margin: 0 auto; background: #fff; border-radius: 1rem; padding: 2rem; text-align: center; box-shadow: 0 4px 24px rgba(0,0,0,.06); }
        h1 { color: #047857; margin: 0 0 .5rem; }
        p { color: #475569; }
    </style>
</head>
<body>
    <div class="card">
        <h1>✓ Payment received</h1>
        <p><strong>{{ $invoice->invoice_number }}</strong></p>
        <p>{{ number_format($invoice->amount, 0) }} BDT · {{ $invoice->paid_at?->format('d M Y H:i') }}</p>
        @if ($invoice->payment_reference)
            <p style="font-size:.85rem;">Ref: {{ $invoice->payment_reference }}</p>
        @endif
    </div>
</body>
</html>
