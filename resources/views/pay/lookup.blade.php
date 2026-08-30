<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Pay bill') }} — {{ site_brand() }}</title>
    <link rel="shortcut icon" href="{{ site_image(siteUrlSettings('site_favicon'), 'images/favicon.png') }}" type="image/x-icon">
    <style>
        :root { --teal:#06ad73; --navy:#1e3a5f; --bg:#f4f7fb; --card:#fff; --muted:#64748b; }
        * { box-sizing: border-box; }
        body { margin:0; min-height:100vh; font-family: Inter, system-ui, sans-serif; background: var(--bg); color: var(--navy); display:flex; align-items:center; justify-content:center; padding:1.5rem; }
        .card { width:100%; max-width:420px; background:var(--card); border-radius:1.25rem; padding:2rem 1.6rem; box-shadow:0 16px 40px rgba(30,58,95,.08); }
        .brand { color:var(--teal); font-weight:800; letter-spacing:.02em; margin:0 0 .35rem; }
        h1 { margin:0 0 .5rem; font-size:1.55rem; }
        p { color:var(--muted); font-size:.95rem; line-height:1.5; margin:0 0 1.4rem; }
        label { display:block; font-size:.8rem; font-weight:700; margin-bottom:.4rem; }
        input { width:100%; border:1px solid #d7e0ea; border-radius:.7rem; padding:.8rem .9rem; font-size:1rem; }
        input:focus { outline:2px solid var(--teal); border-color:transparent; }
        .err { color:#dc2626; font-size:.82rem; margin-top:.4rem; }
        button { width:100%; margin-top:1rem; background:var(--teal); color:#fff; border:0; border-radius:.7rem; padding:.85rem; font-weight:700; font-size:1rem; cursor:pointer; }
        button:hover { filter:brightness(.95); }
        .links { margin-top:1.1rem; text-align:center; font-size:.85rem; }
        .links a { color:var(--teal); text-decoration:none; font-weight:600; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">{{ site_brand() }}</div>
        <h1>{{ __('Pay your bill') }}</h1>
        <p>{{ __('Enter your User ID, PPPoE username, or mobile. Then pay with bKash / Nagad — paid automatically.') }}</p>
        <form method="post" action="{{ route('pay.find') }}">
            @csrf
            <label for="lookup">{{ __('User ID') }}</label>
            <input id="lookup" name="lookup" value="{{ old('lookup') }}" placeholder="FCNET100" autofocus required>
            @error('lookup') <div class="err">{{ $message }}</div> @enderror
            <button type="submit">{{ __('Find bill') }}</button>
        </form>
        <div class="links">
            <a href="{{ url('/recharge/voucher') }}">{{ __('Recharge with voucher') }}</a>
        </div>
    </div>
</body>
</html>
