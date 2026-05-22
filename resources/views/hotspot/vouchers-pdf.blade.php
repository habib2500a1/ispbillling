<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10pt; color: #111; }
        h1 { font-size: 14pt; margin: 0 0 4px; }
        .meta { font-size: 8pt; color: #555; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        td { width: 33.33%; vertical-align: top; padding: 6px; }
        .card {
            border: 1px dashed #333;
            border-radius: 4px;
            padding: 10px 8px;
            min-height: 88px;
        }
        .code { font-size: 13pt; font-weight: bold; letter-spacing: 1px; font-family: monospace; }
        .label { font-size: 7pt; color: #666; text-transform: uppercase; margin-top: 6px; }
        .value { font-size: 9pt; font-family: monospace; }
        .hours { font-size: 8pt; margin-top: 4px; }
    </style>
</head>
<body>
    <h1>{{ $company }} — Hotspot vouchers</h1>
    <p class="meta">Printed {{ $printedAt }} · {{ $vouchers->count() }} card(s)</p>

    <table>
        @foreach ($vouchers->chunk(3) as $row)
            <tr>
                @foreach ($row as $voucher)
                    <td>
                        <div class="card">
                            <div class="code">{{ $voucher->code }}</div>
                            <div class="hours">
                                {{ $voucher->duration_hours }}h
                                @if ($voucher->data_limit_mb)
                                    · {{ number_format($voucher->data_limit_mb) }} MB
                                @endif
                                @if ($voucher->price > 0)
                                    · {{ number_format((float) $voucher->price, 2) }} BDT
                                @endif
                            </div>
                            @if ($voucher->hotspot_username)
                                <div class="label">Wi‑Fi user</div>
                                <div class="value">{{ $voucher->hotspot_username }}</div>
                                <div class="label">Password</div>
                                <div class="value">{{ $voucher->hotspot_password }}</div>
                            @else
                                <div class="label">Redeem at portal</div>
                                <div class="value" style="font-size:8pt;">Enter code on hotspot login page</div>
                            @endif
                            @if ($voucher->batch_name)
                                <div class="label">Batch</div>
                                <div class="value" style="font-size:8pt;">{{ $voucher->batch_name }}</div>
                            @endif
                        </div>
                    </td>
                @endforeach
                @for ($i = $row->count(); $i < 3; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
