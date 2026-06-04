<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Receipt {{ $sale->sale_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; }
        .company-letterhead { margin-bottom: 12px; border-bottom: 2px solid #ea580c; padding-bottom: 8px; }
        .company-letterhead__logo { max-height: 48px; max-width: 160px; margin-bottom: 4px; }
        .company-letterhead__name { font-size: 14pt; font-weight: bold; margin: 0; color: #9a3412; }
        .company-letterhead__line { font-size: 8pt; color: #64748b; margin: 2px 0 0; }
        h1 { font-size: 16pt; margin: 12px 0 4px; color: #ea580c; }
        .muted { color: #64748b; font-size: 9pt; }
        table.meta { width: 100%; border-collapse: collapse; margin: 12px 0; }
        table.meta td { padding: 4px 8px 4px 0; vertical-align: top; font-size: 10pt; }
        table.meta td:first-child { color: #64748b; width: 28%; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 16px; }
        table.items th {
            background: #fff7ed;
            font-size: 9pt;
            text-transform: uppercase;
            padding: 8px 10px;
            text-align: left;
            border-bottom: 2px solid #fdba74;
        }
        table.items td { padding: 8px 10px; border-bottom: 1px solid #e2e8f0; vertical-align: top; }
        table.items .num { text-align: right; white-space: nowrap; }
        table.items .thumb { width: 36px; }
        table.items .thumb img { width: 32px; height: 32px; object-fit: cover; border-radius: 4px; }
        .totals { width: 100%; margin-top: 12px; }
        .totals td { padding: 4px 0; }
        .totals .label { color: #64748b; }
        .totals .grand { font-size: 14pt; font-weight: bold; color: #0f172a; border-top: 2px solid #0f172a; padding-top: 8px; }
        .totals .grand .num { text-align: right; }
    </style>
</head>
<body>
    <div class="company-letterhead">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="" class="company-letterhead__logo">
        @endif
        <p class="company-letterhead__name">{{ $company }}</p>
        @if ($companyPhone)
            <p class="company-letterhead__line">{{ $companyPhone }}</p>
        @endif
        @if ($companyAddress)
            <p class="company-letterhead__line">{{ $companyAddress }}</p>
        @endif
    </div>

    <h1>Retail sale receipt</h1>
    <p class="muted">{{ $sale->sold_at?->timezone(config('app.timezone'))->format('d M Y H:i') }}</p>

    <table class="meta">
        <tr>
            <td>Sale number</td>
            <td><strong>{{ $sale->sale_number }}</strong></td>
        </tr>
        <tr>
            <td>Channel</td>
            <td>{{ ucfirst($sale->channel) }}</td>
        </tr>
        <tr>
            <td>Payment</td>
            <td>{{ strtoupper($sale->payment_method) }}</td>
        </tr>
        @if ($sale->warehouse)
            <tr>
                <td>Warehouse</td>
                <td>{{ $sale->warehouse->name }}</td>
            </tr>
        @endif
        <tr>
            <td>Customer</td>
            <td>{{ $sale->customer_name ?: 'Walk-in' }}</td>
        </tr>
        @if (filled($sale->customer_phone))
            <tr>
                <td>Phone</td>
                <td>{{ $sale->customer_phone }}</td>
            </tr>
        @endif
        @if ($sale->recorder)
            <tr>
                <td>Staff</td>
                <td>{{ $sale->recorder->name }}</td>
            </tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th class="thumb"></th>
                <th>Item</th>
                <th class="num">Qty</th>
                <th class="num">Unit (BDT)</th>
                <th class="num">Amount (BDT)</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($sale->items as $item)
                @php
                    $img = $item->product?->imageUrl();
                @endphp
                <tr>
                    <td class="thumb">
                        @if ($img)
                            <img src="{{ $img }}" alt="">
                        @endif
                    </td>
                    <td>{{ $item->description }}</td>
                    <td class="num">{{ $item->quantity }}</td>
                    <td class="num">{{ number_format((float) $item->unit_price, 2) }}</td>
                    <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td class="label" style="width:70%;">Subtotal</td>
            <td class="num" style="width:30%; text-align:right;">{{ number_format((float) $sale->subtotal, 2) }} BDT</td>
        </tr>
        @if ((float) $sale->discount > 0.009)
            <tr>
                <td class="label">Discount</td>
                <td class="num" style="text-align:right;">−{{ number_format((float) $sale->discount, 2) }} BDT</td>
            </tr>
        @endif
        <tr class="grand">
            <td>Total paid</td>
            <td class="num">{{ number_format((float) $sale->total, 2) }} BDT</td>
        </tr>
    </table>

    @if (filled($sale->notes))
        <p class="muted" style="margin-top:16px;"><strong>Notes:</strong> {{ $sale->notes }}</p>
    @endif

    <p class="muted" style="margin-top: 24px;">{{ \App\Support\CompanyBranding::invoiceFooter() }}</p>
</body>
</html>
