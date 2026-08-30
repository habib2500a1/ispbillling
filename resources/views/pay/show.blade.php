<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Pay') }} {{ $customer->customer_unique_id }} — {{ site_brand() }}</title>
    <link rel="shortcut icon" href="{{ site_image(siteUrlSettings('site_favicon'), 'images/favicon.png') }}" type="image/x-icon">
    <style>
        :root { --teal:#06ad73; --navy:#1e3a5f; --bg:#f4f7fb; --card:#fff; --muted:#64748b; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; font-family: Inter, system-ui, sans-serif; background: var(--bg); color: var(--navy); display:flex; align-items:center; justify-content:center; padding:1.5rem; }
        .card { width:100%; max-width:460px; background:var(--card); border-radius:1.25rem; padding:1.7rem 1.5rem; box-shadow:0 16px 40px rgba(30,58,95,.08); }
        .brand { color:var(--teal); font-weight:800; margin:0 0 .2rem; }
        h1 { margin:0 0 1rem; font-size:1.35rem; }
        .ok { background:#ecfdf5; color:#047857; padding:.7rem .85rem; border-radius:.7rem; margin-bottom:1rem; font-size:.9rem; }
        .bad { background:#fef2f2; color:#b91c1c; padding:.7rem .85rem; border-radius:.7rem; margin-bottom:1rem; font-size:.9rem; }
        .row { display:flex; justify-content:space-between; gap:1rem; padding:.45rem 0; border-bottom:1px solid #eef2f6; font-size:.92rem; }
        .row span { color:var(--muted); }
        .due { font-size:1.6rem; font-weight:800; color:{{ $due > 0 ? '#dc2626' : 'var(--teal)' }}; margin:.3rem 0 1rem; }
        label { display:block; font-size:.8rem; font-weight:700; margin:.8rem 0 .35rem; }
        input, select { width:100%; border:1px solid #d7e0ea; border-radius:.7rem; padding:.75rem .85rem; font-size:1rem; }
        button { width:100%; margin-top:1rem; background:var(--teal); color:#fff; border:0; border-radius:.7rem; padding:.85rem; font-weight:700; cursor:pointer; }
        .ghost { display:block; text-align:center; margin-top:.9rem; color:var(--muted); text-decoration:none; font-size:.85rem; }
        .onu { margin-top:.8rem; background:#f8fafc; border-radius:.8rem; padding:.7rem .85rem; font-size:.85rem; color:var(--muted); }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">{{ site_brand() }}</div>
        <h1>{{ $customer->customer_name }}</h1>

        @if(session('success')) <div class="ok">{{ session('success') }}</div> @endif
        @if(session('error')) <div class="bad">{{ session('error') }}</div> @endif

        <div class="row"><span>{{ __('User ID') }}</span><strong>{{ $customer->customer_unique_id }}</strong></div>
        @if($customer->pppUser?->username)
            <div class="row"><span>{{ __('PPPoE') }}</span><strong>{{ $customer->pppUser->username }}</strong></div>
        @endif
        @if($customer->package?->package)
            <div class="row"><span>{{ __('Package') }}</span><strong>{{ $customer->package->package }}</strong></div>
        @endif
        <div class="row"><span>{{ __('Monthly') }}</span><strong>৳{{ number_format($rent) }}</strong></div>
        <div class="row"><span>{{ __('Expire') }}</span><strong>{{ $billing?->auto_disable_date ? \Carbon\Carbon::parse($billing->auto_disable_date)->format('d M Y') : '—' }}</strong></div>
        <div class="due">৳{{ number_format($due) }} <small style="font-size:.7rem;font-weight:600;color:var(--muted);">{{ __('due') }}</small></div>

        @if($onu)
            <div class="onu">
                {{ __('ONU') }} {{ $onu->serial_number ?: $onu->mac_address ?: '—' }}
                · {{ $onu->oper_status ?: '—' }}
                @if($onu->rx_power_dbm) · RX {{ $onu->rx_power_dbm }} dBm @endif
            </div>
        @endif

        @if($gateways['bkash'] || $gateways['nagad'] || $gateways['sslcommerz'])
            <form method="post" action="{{ route('pay.checkout', $customer->customer_unique_id) }}">
                @csrf
                <label>{{ __('Amount') }}</label>
                <input type="number" name="amount" min="1" step="1" value="{{ old('amount', (int) $amount) }}" required>
                <label>{{ __('Pay with') }}</label>
                <select name="gateway" required>
                    @if($gateways['bkash']) <option value="bkash">bKash</option> @endif
                    @if($gateways['nagad']) <option value="nagad">Nagad</option> @endif
                    @if($gateways['sslcommerz']) <option value="sslcommerz">Card / SSLCommerz</option> @endif
                </select>
                @error('amount') <div class="bad">{{ $message }}</div> @enderror
                <button type="submit">{{ __('Pay now') }}</button>
            </form>
        @else
            <div class="bad">{{ __('Online payment is not enabled yet. Use the office collection or a voucher.') }}</div>
        @endif

        <a class="ghost" href="{{ route('pay.lookup') }}">{{ __('Pay another ID') }}</a>
    </div>
</body>
</html>
