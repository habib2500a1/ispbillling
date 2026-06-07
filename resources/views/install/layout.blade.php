<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ISP Platform Setup</title>
    <style>
        :root { color-scheme: light; --bg:#f4f7fb; --card:#fff; --text:#0f172a; --muted:#64748b; --brand:#2563eb; --ok:#16a34a; --bad:#dc2626; --border:#e2e8f0; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: ui-sans-serif, system-ui, -apple-system, Segoe UI, Roboto, sans-serif; background:var(--bg); color:var(--text); }
        .wrap { max-width: 760px; margin: 32px auto; padding: 0 16px 48px; }
        .card { background:var(--card); border:1px solid var(--border); border-radius: 14px; padding: 24px; box-shadow: 0 8px 24px rgba(15,23,42,.06); }
        h1 { margin: 0 0 8px; font-size: 1.5rem; }
        p.lead { margin: 0 0 20px; color: var(--muted); line-height: 1.5; }
        .steps { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .step { padding:6px 12px; border-radius:999px; font-size:.85rem; border:1px solid var(--border); color:var(--muted); background:#fff; }
        .step.active { background:#dbeafe; color:#1d4ed8; border-color:#93c5fd; font-weight:600; }
        .step.done { background:#dcfce7; color:#166534; border-color:#86efac; }
        label { display:block; font-size:.9rem; font-weight:600; margin: 14px 0 6px; }
        input, select { width:100%; padding:10px 12px; border:1px solid var(--border); border-radius:8px; font-size:1rem; }
        .row { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
        @media (max-width:640px){ .row { grid-template-columns:1fr; } }
        .btn { display:inline-block; margin-top:20px; padding:11px 18px; border-radius:8px; border:0; background:var(--brand); color:#fff; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn.secondary { background:#e2e8f0; color:#0f172a; }
        .btn:disabled { opacity:.55; cursor:not-allowed; }
        .alert { padding:12px 14px; border-radius:8px; margin-bottom:16px; }
        .alert.ok { background:#dcfce7; color:#166534; }
        .alert.bad { background:#fee2e2; color:#991b1b; }
        .alert.info { background:#eff6ff; color:#1e40af; }
        table { width:100%; border-collapse: collapse; }
        th, td { text-align:left; padding:10px 8px; border-bottom:1px solid var(--border); font-size:.92rem; }
        .ok { color:var(--ok); font-weight:700; }
        .bad { color:var(--bad); font-weight:700; }
        .errors { color:var(--bad); font-size:.9rem; margin-top:8px; }
        code { background:#f1f5f9; padding:2px 6px; border-radius:4px; font-size:.85rem; }
    </style>
</head>
<body>
<div class="wrap">
    @php
        $route = request()->route()?->getName() ?? '';
        $steps = [
            'install.welcome' => 'Requirements',
            'install.permissions' => 'Permissions',
            'install.database' => 'Database',
            'install.admin' => 'Admin',
            'install.complete' => 'Done',
        ];
        $order = array_keys($steps);
        $currentIndex = array_search($route, $order, true);
    @endphp
    <div class="steps">
        @foreach($steps as $name => $label)
            @php
                $idx = array_search($name, $order, true);
                $class = $idx === $currentIndex ? 'active' : ($idx < $currentIndex ? 'done' : '');
            @endphp
            <span class="step {{ $class }}">{{ $label }}</span>
        @endforeach
    </div>
    <div class="card">
        @if (session('status'))
            <div class="alert ok">{{ session('status') }}</div>
        @endif
        @yield('content')
    </div>
</div>
</body>
</html>
