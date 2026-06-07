<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Please wait' }}</title>
    @if (! empty($autoRefresh))
        <meta http-equiv="refresh" content="5">
    @endif
    <style>
        body { font-family: system-ui, sans-serif; background: #0f172a; color: #e2e8f0; display: flex; min-height: 100vh; align-items: center; justify-content: center; margin: 0; padding: 1.5rem; }
        .box { max-width: 28rem; background: #1e293b; border: 1px solid #334155; border-radius: 1rem; padding: 1.5rem; text-align: center; }
        h1 { font-size: 1.25rem; margin: 0 0 0.75rem; }
        p { margin: 0.5rem 0; font-size: 0.95rem; color: #94a3b8; line-height: 1.55; }
        .spinner { width: 2rem; height: 2rem; border: 3px solid #334155; border-top-color: #818cf8; border-radius: 50%; margin: 0 auto 1rem; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        a { color: #a5b4fc; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="box">
        <div class="spinner" aria-hidden="true"></div>
        <h1>{{ $title ?? 'Please wait' }}</h1>
        <p>{{ $message ?? 'The system is updating. This page will refresh automatically.' }}</p>
        @if (! empty($autoRefresh))
            <p>Auto-refresh in 5 seconds…</p>
        @endif
        <p><a href="{{ url()->current() }}">Refresh now</a></p>
    </div>
</body>
</html>
