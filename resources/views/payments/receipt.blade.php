<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $payment->receipt_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; }
        .company-letterhead { margin-bottom: 12px; border-bottom: 2px solid #b45309; padding-bottom: 8px; }
        .company-letterhead__logo { max-height: 48px; max-width: 160px; margin-bottom: 4px; }
        .company-letterhead__name { font-size: 14pt; font-weight: bold; margin: 0; }
        .company-letterhead__line { font-size: 8pt; color: #64748b; margin: 2px 0 0; }
        h1 { font-size: 16pt; margin: 12px 0 4px; color: #b45309; }
        .muted { color: #64748b; font-size: 9pt; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { padding: 8px 10px; text-align: left; border-bottom: 1px solid #e2e8f0; }
        th { background: #f8fafc; font-size: 9pt; text-transform: uppercase; }
        .amount { font-size: 16pt; font-weight: bold; text-align: right; }
        .refund { color: #dc2626; }
    </style>
</head>
<body>
    @include('partials.company-letterhead')
    <h1>Payment receipt</h1>
    <p class="muted">{{ now()->format('d M Y H:i') }}</p>

    <table>
        <tr>
            <th>Receipt #</th>
            <td><strong>{{ $payment->receipt_number }}</strong></td>
        </tr>
        <tr>
            <th>Subscriber</th>
            <td>{{ $payment->customer?->name }} ({{ $payment->customer?->customer_code }})</td>
        </tr>
        <tr>
            <th>Type</th>
            <td>{{ $payment->typeLabel() }}</td>
        </tr>
        <tr>
            <th>Method</th>
            <td>{{ $payment->methodLabel() }}</td>
        </tr>
        @if ($payment->invoice)
            <tr>
                <th>Invoice</th>
                <td>{{ $payment->invoice->invoice_number }}</td>
            </tr>
        @endif
        <tr>
            <th>Reference</th>
            <td>{{ $payment->reference ?? '—' }}</td>
        </tr>
        @if ($payment->gateway_transaction_id)
            <tr>
                <th>Gateway TX</th>
                <td>{{ $payment->gateway_transaction_id }}</td>
            </tr>
        @endif
        <tr>
            <th>Paid at</th>
            <td>{{ $payment->paid_at?->format('d M Y H:i') ?? '—' }}</td>
        </tr>
        @if ($payment->notes)
            <tr>
                <th>Notes</th>
                <td>{{ $payment->notes }}</td>
            </tr>
        @endif
    </table>

    <p class="amount {{ $payment->isRefund() ? 'refund' : '' }}">
        {{ $payment->isRefund() ? '−' : '' }}{{ number_format((float) $payment->amount, 2) }} BDT
    </p>

    <p class="muted" style="margin-top: 24px;">{{ \App\Support\CompanyBranding::invoiceFooter() }}</p>
</body>
</html>
