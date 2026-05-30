<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: dejavusans, sans-serif;
            font-size: 11px;
            color: #1a1a1a;
            line-height: 1.45;
            margin: 0;
            padding: 0;
        }
        .company-letterhead { margin-bottom: 14px; border-bottom: 2px solid #333; padding-bottom: 10px; }
        .company-letterhead__logo { max-height: 52px; max-width: 180px; margin-bottom: 6px; }
        .company-letterhead__name { font-size: 16px; font-weight: bold; margin: 0 0 2px 0; }
        .company-letterhead__tagline { font-size: 10px; color: #555; margin: 0 0 4px 0; }
        .company-letterhead__line { font-size: 9px; color: #444; margin: 0 0 2px 0; }
        .doc-title { font-size: 18px; font-weight: bold; margin: 0 0 4px 0; letter-spacing: 0.04em; }
        .doc-subtitle { font-size: 10px; color: #666; margin: 0 0 12px 0; }
        .invoice-meta {
            width: 100%;
            margin-bottom: 20px;
            border-collapse: collapse;
        }
        .invoice-meta td {
            width: 50%;
            vertical-align: top;
            padding: 0 12px 0 0;
        }
        .invoice-meta .label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: #666;
            margin: 0 0 2px 0;
        }
        .invoice-meta .value {
            font-size: 12px;
            margin: 0 0 10px 0;
        }
        .bill-to {
            background: #f7f7f7;
            padding: 10px 12px;
            border: 1px solid #ddd;
        }
        .bill-to .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            margin: 0 0 6px 0;
        }
        table.lines {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        table.lines th {
            background: #333;
            color: #fff;
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding: 8px 10px;
            text-align: left;
            font-weight: bold;
        }
        table.lines th.right, table.lines td.right { text-align: right; }
        table.lines td {
            border-bottom: 1px solid #e0e0e0;
            padding: 8px 10px;
            vertical-align: top;
        }
        table.lines tbody tr:nth-child(even) { background: #fafafa; }
        .totals-wrap {
            width: 100%;
            margin-top: 16px;
        }
        table.totals {
            width: 260px;
            margin-left: auto;
            border-collapse: collapse;
        }
        table.totals td {
            padding: 5px 10px;
            border-bottom: 1px solid #eee;
        }
        table.totals td.label { text-align: left; color: #444; }
        table.totals td.amount { text-align: right; font-variant-numeric: tabular-nums; }
        table.totals tr.grand td {
            border-top: 2px solid #333;
            border-bottom: 2px solid #333;
            font-weight: bold;
            font-size: 12px;
            padding-top: 8px;
            padding-bottom: 8px;
        }
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            font-size: 9px;
            text-transform: uppercase;
            border: 1px solid #ccc;
            background: #fff;
        }
        .notes, .terms {
            margin-top: 16px;
            padding: 10px 12px;
            border: 1px dashed #ccc;
            font-size: 10px;
        }
        .notes .label, .terms .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            margin: 0 0 6px 0;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 9px;
            color: #666;
            text-align: center;
        }
    </style>
</head>
<body>
    @include('partials.company-letterhead')

    <p class="doc-title">INVOICE</p>
    <p class="doc-subtitle">Invoice #{{ $invoice->invoice_number }}</p>

    <table class="invoice-meta">
        <tr>
            <td>
                <div class="bill-to">
                    <p class="label">Bill to</p>
                    <p class="value" style="margin:0;font-size:13px;font-weight:bold;">{{ $invoice->customer?->name ?? '—' }}</p>
                    @if ($invoice->customer?->phone)
                        <p class="value" style="margin:4px 0 0 0;">Phone: {{ $invoice->customer->phone }}</p>
                    @endif
                    @if ($invoice->customer?->email)
                        <p class="value" style="margin:2px 0 0 0;">Email: {{ $invoice->customer->email }}</p>
                    @endif
                </div>
            </td>
            <td>
                <p class="label">Issue date</p>
                <p class="value">{{ $invoice->issue_date?->format('F j, Y') ?? '—' }}</p>
                <p class="label">Due date</p>
                <p class="value">{{ $invoice->due_date?->format('F j, Y') ?? '—' }}</p>
                <p class="label">Billing period</p>
                <p class="value">{{ $invoice->period_start?->format('M j, Y') ?? '—' }} &ndash; {{ $invoice->period_end?->format('M j, Y') ?? '—' }}</p>
                <p class="label">Status</p>
                <p class="value"><span class="status-badge">{{ $invoice->status }}</span></p>
            </td>
        </tr>
    </table>

    <table class="lines">
        <thead>
            <tr>
                <th style="width:52%;">Description</th>
                <th class="right" style="width:12%;">Qty</th>
                <th class="right" style="width:18%;">Unit price</th>
                <th class="right" style="width:18%;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoice->items as $item)
                <tr>
                    <td>{{ $item->description }}</td>
                    <td class="right">{{ number_format((float) $item->quantity, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="right">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4" style="text-align:center;color:#888;padding:16px;">No line items.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals-wrap">
        <table class="totals">
            <tr>
                <td class="label">Subtotal</td>
                <td class="amount">{{ number_format((float) $invoice->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Discount</td>
                <td class="amount">{{ number_format((float) $invoice->discount_amount, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Tax / VAT</td>
                <td class="amount">{{ number_format((float) $invoice->tax_amount, 2) }}</td>
            </tr>
            <tr class="grand">
                <td class="label">Total due</td>
                <td class="amount">{{ number_format((float) $invoice->total, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Amount paid</td>
                <td class="amount">{{ number_format((float) $invoice->amount_paid, 2) }}</td>
            </tr>
            <tr>
                <td class="label">Balance</td>
                <td class="amount">{{ number_format(max(0, (float) $invoice->total - (float) $invoice->amount_paid), 2) }}</td>
            </tr>
        </table>
    </div>

    @if ($invoice->notes)
        <div class="notes">
            <p class="label">Notes</p>
            <p style="margin:0;">{{ $invoice->notes }}</p>
        </div>
    @endif

    @if (\App\Support\CompanyBranding::invoiceTerms())
        <div class="terms">
            <p class="label">Terms &amp; conditions</p>
            <p style="margin:0;">{{ \App\Support\CompanyBranding::invoiceTerms() }}</p>
        </div>
    @endif

    <div class="footer">
        {{ $invoiceFooter ?? \App\Support\CompanyBranding::invoiceFooter() }}
    </div>
</body>
</html>
