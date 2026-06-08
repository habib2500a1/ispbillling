<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Receipt {{ $sale->sale_number }}</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
            font-size: 12px;
            line-height: 1.45;
            color: #0f172a;
            background: #f1f5f9;
        }
        .no-print {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            padding: 1rem;
            justify-content: center;
            background: #fff;
            border-bottom: 1px solid #e2e8f0;
        }
        .no-print a,
        .no-print button {
            font-family: system-ui, sans-serif;
            font-size: 0.85rem;
            font-weight: 600;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            background: #fff;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
        }
        .no-print button.primary,
        .no-print a.primary {
            background: #ea580c;
            border-color: #ea580c;
            color: #fff;
        }
        .receipt {
            width: 80mm;
            max-width: 100%;
            margin: 1rem auto;
            padding: 10px 8px 16px;
            background: #fff;
            box-shadow: 0 4px 24px rgba(15, 23, 42, 0.12);
        }
        .receipt__logo {
            display: block;
            max-width: 56mm;
            max-height: 14mm;
            margin: 0 auto 6px;
        }
        .receipt__accent {
            height: 3px;
            background: linear-gradient(90deg, #f97316, #ea580c);
            margin: 0 0 8px;
            border-radius: 2px;
        }
        .receipt__brand {
            text-align: center;
            font-weight: 800;
            font-size: 13px;
            margin: 0 0 2px;
            letter-spacing: 0.02em;
        }
        .receipt__tagline {
            text-align: center;
            font-size: 9px;
            color: #94a3b8;
            margin: 0 0 6px;
            text-transform: uppercase;
            letter-spacing: 0.12em;
        }
        .receipt__meta {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            margin: 0 0 8px;
        }
        .receipt__title {
            text-align: center;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            border-top: 1px dashed #cbd5e1;
            border-bottom: 1px dashed #cbd5e1;
            padding: 6px 0;
            margin: 0 0 8px;
        }
        .receipt__row {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin: 2px 0;
        }
        .receipt__row span:last-child {
            text-align: right;
            font-weight: 600;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin: 8px 0;
            font-size: 11px;
        }
        table.items th {
            text-align: left;
            border-bottom: 1px solid #0f172a;
            padding: 4px 0;
            font-size: 10px;
        }
        table.items td {
            padding: 4px 0;
            vertical-align: top;
            border-bottom: 1px dotted #e2e8f0;
        }
        table.items .num {
            text-align: right;
            white-space: nowrap;
        }
        .totals {
            border-top: 1px solid #0f172a;
            padding-top: 6px;
            margin-top: 6px;
        }
        .totals__grand {
            font-size: 14px;
            font-weight: 800;
            margin-top: 4px;
        }
        .receipt__foot {
            text-align: center;
            font-size: 10px;
            color: #64748b;
            margin-top: 12px;
            border-top: 1px dashed #cbd5e1;
            padding-top: 8px;
        }
        @media print {
            body { background: #fff; }
            .no-print { display: none !important; }
            .receipt {
                width: 80mm;
                margin: 0;
                padding: 0;
                box-shadow: none;
            }
            @page {
                size: 80mm auto;
                margin: 4mm;
            }
        }
    </style>
</head>
<body>
    <div class="no-print">
        <button type="button" class="primary" onclick="window.print()">Print receipt</button>
        <a href="{{ $backUrl }}">Back to sale</a>
        <a href="{{ \App\Filament\Resources\InventorySaleResource::getUrl() }}">All sales</a>
    </div>

    <article class="receipt">
        <div class="receipt__accent" aria-hidden="true"></div>
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="" class="receipt__logo">
        @endif
        <p class="receipt__brand">{{ $company }}</p>
        <p class="receipt__tagline">Inventory POS</p>
        @if ($companyPhone || $companyAddress)
            <p class="receipt__meta">
                {{ collect([$companyPhone, $companyAddress])->filter()->implode(' · ') }}
            </p>
        @endif

        <p class="receipt__title">Retail sale receipt</p>

        <div class="receipt__row"><span>Sale #</span><span>{{ $sale->sale_number }}</span></div>
        <div class="receipt__row"><span>Date</span><span>{{ $sale->sold_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') }}</span></div>
        <div class="receipt__row"><span>Channel</span><span>{{ ucfirst($sale->channel) }}</span></div>
        <div class="receipt__row"><span>Payment</span><span>{{ strtoupper($sale->payment_method) }}</span></div>
        @if ($sale->warehouse)
            <div class="receipt__row"><span>Warehouse</span><span>{{ $sale->warehouse->name }}</span></div>
        @endif
        @if (filled($sale->customer_name))
            <div class="receipt__row"><span>Customer</span><span>{{ $sale->customer_name }}</span></div>
        @endif
        @if (filled($sale->customer_phone))
            <div class="receipt__row"><span>Phone</span><span>{{ $sale->customer_phone }}</span></div>
        @endif
        @if ($sale->recorder)
            <div class="receipt__row"><span>Staff</span><span>{{ $sale->recorder->name }}</span></div>
        @endif

        <table class="items">
            <thead>
                <tr>
                    <th>Item</th>
                    <th class="num">Qty</th>
                    <th class="num">Rate</th>
                    <th class="num">Amt</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($sale->items as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td class="num">{{ $item->quantity }}</td>
                        <td class="num">{{ number_format((float) $item->unit_price, 0) }}</td>
                        <td class="num">{{ number_format((float) $item->line_total, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="totals">
            <div class="receipt__row"><span>Subtotal</span><span>{{ number_format((float) $sale->subtotal, 2) }} BDT</span></div>
            @if ((float) $sale->discount > 0.009)
                <div class="receipt__row"><span>Discount</span><span>−{{ number_format((float) $sale->discount, 2) }} BDT</span></div>
            @endif
            <div class="receipt__row totals__grand"><span>TOTAL</span><span>{{ number_format((float) $sale->total, 2) }} BDT</span></div>
        </div>

        @if (filled($sale->notes))
            <div class="receipt__row" style="margin-top:8px;"><span>Notes</span><span>{{ $sale->notes }}</span></div>
        @endif

        <p class="receipt__foot">Thank you · {{ $company }}</p>
    </article>

    @if ($autoPrint)
        <script>window.addEventListener('load', () => window.print());</script>
    @endif
</body>
</html>
