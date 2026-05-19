<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title>Hotspot Wi‑Fi — {{ config('app.name') }}</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('css/portal.css') }}?v=4">
    <style>
        body { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(145deg, #0f766e 0%, #134e4a 50%, #1e1b4b 100%); font-family: Outfit, sans-serif; padding: 1rem; }
        .hotspot-card { width: 100%; max-width: 24rem; border-radius: 1.25rem; background: rgba(255,255,255,0.97); padding: 2rem; box-shadow: 0 25px 50px rgba(0,0,0,0.25); }
        .hotspot-title { font-size: 1.5rem; font-weight: 700; color: #0f172a; text-align: center; }
        .hotspot-sub { margin-top: 0.5rem; font-size: 0.875rem; color: #64748b; text-align: center; }
        .hotspot-input { width: 100%; margin-top: 1.25rem; border-radius: 0.75rem; border: 1px solid #cbd5e1; padding: 0.875rem 1rem; font-size: 1.125rem; letter-spacing: 0.05em; text-align: center; text-transform: uppercase; }
        .hotspot-btn { width: 100%; margin-top: 1rem; border: none; border-radius: 0.75rem; background: #0d9488; color: white; font-weight: 600; padding: 0.875rem; cursor: pointer; }
        .hotspot-btn:hover { background: #0f766e; }
        .hotspot-ok { margin-top: 1rem; border-radius: 0.75rem; background: #ecfdf5; padding: 1rem; font-size: 0.875rem; color: #065f46; }
        .hotspot-err { margin-top: 1rem; border-radius: 0.75rem; background: #fef2f2; padding: 0.75rem; font-size: 0.875rem; color: #991b1b; }
    </style>
</head>
<body>
    <div class="hotspot-card">
        <p class="hotspot-title">Wi‑Fi Hotspot</p>
        <p class="hotspot-sub">{{ $welcome }}</p>

        @if (session('hotspot_success'))
            @php $ok = session('hotspot_success'); @endphp
            <div class="hotspot-ok">
                <p class="font-semibold">{{ $ok['message'] }}</p>
                @if (! empty($ok['voucher']['duration_hours']))
                    <p class="mt-1">Duration: {{ $ok['voucher']['duration_hours'] }} hours</p>
                @endif
            </div>
        @endif

        @if ($errors->any())
            <div class="hotspot-err">{{ $errors->first() }}</div>
        @endif

        <form method="post" action="{{ route('hotspot.redeem') }}">
            @csrf
            <input type="text" name="code" class="hotspot-input" placeholder="XXXX-XXXX" value="{{ old('code') }}" required autocomplete="off" autofocus>
            <button type="submit" class="hotspot-btn">Connect</button>
        </form>

        <p class="mt-6 text-center text-xs text-slate-500">
            <a href="{{ route('bill-payment.index') }}" class="text-teal-600 hover:underline">Pay bill online</a>
        </p>
    </div>
</body>
</html>
