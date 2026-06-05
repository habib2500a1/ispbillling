<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Money Receipt {{ $payment->receipt_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #1e293b; margin: 0; padding: 0; }
        .letterhead { text-align: center; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px solid #cbd5e1; }
        .letterhead__logo { max-height: 52px; max-width: 180px; margin-bottom: 4px; }
        .letterhead__name { font-size: 13pt; font-weight: bold; margin: 0; text-transform: capitalize; }
        .letterhead__line { font-size: 8.5pt; color: #64748b; margin: 2px 0 0; }
        .title-row { margin: 14px 0 12px; text-align: center; }
        .title-row__line { border-top: 1px solid #94a3b8; margin: 0; }
        .title-row__text { font-size: 15pt; font-weight: bold; margin: 8px 0; letter-spacing: 0.5px; }
        .panel { background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 4px; padding: 10px 12px; margin-bottom: 10px; }
        .panel__grid { width: 100%; border-collapse: collapse; }
        .panel__grid td { padding: 4px 6px; vertical-align: top; font-size: 9.5pt; }
        .panel__label { color: #64748b; width: 38%; }
        .panel__value { font-weight: 600; color: #0f172a; }
        .amounts { border: 1px solid #e2e8f0; border-radius: 4px; overflow: hidden; margin-bottom: 10px; }
        .amounts__row { display: table; width: 100%; border-bottom: 1px solid #e2e8f0; }
        .amounts__row:last-child { border-bottom: none; }
        .amounts__label, .amounts__value { display: table-cell; padding: 8px 12px; font-size: 9.5pt; }
        .amounts__label { background: #f8fafc; color: #475569; width: 55%; }
        .amounts__value { text-align: right; font-weight: 700; color: #0f172a; }
        .received-by { padding: 8px 12px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 4px; font-size: 9.5pt; margin-bottom: 12px; }
        .footer { border-top: 1px dashed #cbd5e1; padding-top: 10px; text-align: center; font-size: 8.5pt; color: #64748b; }
    </style>
</head>
<body>
    @php
        $presenter = app(\App\Support\StaffPaymentApiPresenter::class);
        $amounts = $presenter->receiptAmounts($payment, $payment->invoice);
        $receivedBy = $payment->recorder?->name ?? '—';
    @endphp

    <div class="letterhead">
        @include('partials.company-letterhead')
    </div>

    <div class="title-row">
        <hr class="title-row__line">
        <div class="title-row__text">Money Receipt</div>
        <hr class="title-row__line">
    </div>

    <div class="panel">
        <table class="panel__grid">
            <tr>
                <td class="panel__label">Client Code</td>
                <td class="panel__value">{{ $payment->customer?->customer_code ?? '—' }}</td>
                <td class="panel__label">Client Name</td>
                <td class="panel__value">{{ $payment->customer?->name ?? '—' }}</td>
            </tr>
            <tr>
                <td class="panel__label">User Name</td>
                <td class="panel__value">{{ $payment->customer?->pppLoginName() ?? '—' }}</td>
                <td class="panel__label">Mobile No.</td>
                <td class="panel__value">{{ $payment->customer?->phone ?? '—' }}</td>
            </tr>
        </table>
    </div>

    <div class="panel">
        <table class="panel__grid">
            <tr>
                <td class="panel__label">Receipt No.</td>
                <td class="panel__value" colspan="3">{{ $payment->receipt_number }}</td>
            </tr>
            <tr>
                <td class="panel__label">Pay. Date</td>
                <td class="panel__value">{{ $payment->paid_at?->format('d M Y, h:i A') ?? '—' }}</td>
                <td class="panel__label">P. Method</td>
                <td class="panel__value">{{ $payment->methodLabel() }}</td>
            </tr>
        </table>
    </div>

    <div class="amounts">
        @foreach ([
            'Total Bill' => $amounts['total_bill'],
            'Paid Amount' => $amounts['paid_amount'],
            'Discount' => $amounts['discount'],
            'Due Amount' => $amounts['due_amount'],
            'VAT Amount' => $amounts['vat_amount'],
            'Advance' => $amounts['advance'],
        ] as $label => $value)
            <div class="amounts__row">
                <div class="amounts__label">{{ $label }}</div>
                <div class="amounts__value">{{ number_format((float) $value, 2) }}</div>
            </div>
        @endforeach
    </div>

    <div class="received-by">
        <strong>Payment Received By:</strong> {{ $receivedBy }}
    </div>

    @if ($footerNote = trim((string) ($invoiceFooter ?? '')))
        <div class="footer">{{ $footerNote }}</div>
    @endif
</body>
</html>
