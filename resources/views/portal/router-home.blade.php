<!DOCTYPE html>
<html lang="bn">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f766e">
    <title>{{ config('app.name') }} — Router Portal</title>
    <style>
        :root { --bg:#0b1220; --card:#111827; --line:#1f2937; --text:#f8fafc; --muted:#94a3b8; --accent:#14b8a6; --warn:#f59e0b; }
        * { box-sizing: border-box; }
        body { margin:0; font-family: system-ui, -apple-system, Segoe UI, sans-serif; background: linear-gradient(180deg,#0b1220,#111827); color:var(--text); min-height:100vh; }
        .wrap { max-width: 480px; margin: 0 auto; padding: 1rem; }
        .brand { text-align:center; margin-bottom:1rem; }
        .brand h1 { font-size:1.05rem; margin:0; }
        .brand p { margin:.35rem 0 0; color:var(--muted); font-size:.82rem; }
        .card { background:var(--card); border:1px solid var(--line); border-radius:14px; padding:1rem; margin-bottom:.85rem; }
        .card h2 { margin:0 0 .75rem; font-size:.95rem; }
        .grid { display:grid; grid-template-columns:1fr 1fr; gap:.65rem; }
        .stat label { display:block; font-size:.72rem; color:var(--muted); text-transform:uppercase; letter-spacing:.04em; }
        .stat strong { display:block; margin-top:.2rem; font-size:1rem; }
        .due strong { color:var(--warn); }
        .btn { display:block; width:100%; text-align:center; border:0; border-radius:10px; padding:.75rem 1rem; font-weight:600; text-decoration:none; cursor:pointer; }
        .btn-primary { background:var(--accent); color:#042f2e; }
        .btn-secondary { background:#1e293b; color:var(--text); border:1px solid var(--line); }
        .btn + .btn { margin-top:.5rem; }
        .field { margin-bottom:.65rem; }
        .field label { display:block; font-size:.78rem; color:var(--muted); margin-bottom:.25rem; }
        .field input { width:100%; border:1px solid var(--line); background:#0f172a; color:var(--text); border-radius:8px; padding:.65rem .75rem; }
        .error { color:#fca5a5; font-size:.82rem; margin-bottom:.5rem; }
        .badge { display:inline-block; font-size:.72rem; padding:.15rem .45rem; border-radius:999px; background:#064e3b; color:#6ee7b7; }
        .badge.off { background:#451a03; color:#fdba74; }
        .ai-log { margin-top:.65rem; max-height:220px; overflow:auto; font-size:.85rem; }
        .ai-msg { padding:.5rem .65rem; border-radius:8px; margin-bottom:.4rem; }
        .ai-msg.user { background:#1e293b; }
        .ai-msg.bot { background:#0f2f2f; border:1px solid #134e4a; }
        .ai-form { display:flex; gap:.5rem; margin-top:.5rem; }
        .ai-form input { flex:1; }
        .ai-form button { width:auto; padding:.65rem .85rem; }
        .hint { font-size:.75rem; color:var(--muted); margin-top:.5rem; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="brand">
        <h1>{{ config('app.name') }}</h1>
        <p>Home router mini portal — বিল, পেমেন্ট ও AI সহায়তা</p>
    </div>

    @if ($customer === null)
        <div class="card">
            <h2>লাইন চিনুন</h2>
            <p class="hint">WiFi থেকে খুললে সাধারণত auto চিনে। না হলে customer code + phone শেষ ৪ ডিজিট দিন।</p>
            @if (! empty($identifyError))
                <p class="error">{{ $identifyError }}</p>
            @endif
            <form method="post" action="{{ route('portal.router-home.identify') }}">
                @csrf
                <div class="field">
                    <label>Customer code</label>
                    <input name="customer_code" required placeholder="যেমন 100234" value="{{ old('customer_code') }}">
                </div>
                <div class="field">
                    <label>Phone শেষ ৪ ডিজিট</label>
                    <input name="phone_tail" required maxlength="8" placeholder="যেমন 4521" inputmode="numeric">
                </div>
                <button class="btn btn-primary" type="submit">চালিয়ে যান</button>
            </form>
            <a class="btn btn-secondary" href="{{ route('portal.login') }}">Full portal login</a>
        </div>
    @else
        <div class="card">
            <h2>{{ $dashboard['name'] ?? $customer->name }}</h2>
            <p class="hint">
                Code: <strong>{{ $dashboard['customer_code'] }}</strong>
                @if (($identifiedBy ?? '') === 'ip')
                    · Auto (WiFi IP)
                @endif
                ·
                <span class="badge {{ ($dashboard['online'] ?? false) ? '' : 'off' }}">
                    {{ ($dashboard['online'] ?? false) ? 'Online' : 'Offline' }}
                </span>
            </p>
            <div class="grid">
                <div class="stat due">
                    <label>বকেয়া</label>
                    <strong>{{ $dashboard['due_formatted'] }}</strong>
                </div>
                <div class="stat">
                    <label>Package</label>
                    <strong>{{ $dashboard['package'] ?? '—' }}</strong>
                </div>
                <div class="stat">
                    <label>IP</label>
                    <strong style="font-family:monospace;font-size:.85rem;">{{ $dashboard['framed_ip'] ?? '—' }}</strong>
                </div>
                <div class="stat">
                    <label>Speed</label>
                    <strong>{{ $dashboard['download_human'] ?? '—' }}</strong>
                </div>
            </div>
            <a class="btn btn-primary" href="{{ $dashboard['pay_url'] }}">এখনই পেমেন্ট করুন</a>
            <a class="btn btn-secondary" href="{{ route('portal.login') }}">Full portal খুলুন</a>
        </div>

        <div class="card" id="ai-card">
            <h2>AI সহায়তা</h2>
            <div class="ai-log" id="ai-log"></div>
            <form class="ai-form" id="ai-form">
                @csrf
                <input type="text" id="ai-q" maxlength="1000" placeholder="যেমন: আমার বিল কত? ইন্টারনেট ধীর কেন?" autocomplete="off">
                <button class="btn btn-primary" type="submit">জিজ্ঞাসা</button>
            </form>
            <p class="hint">বিল, speed, ONU signal, ticket সম্পর্কে জিজ্ঞাসা করুন।</p>
        </div>
    @endif

    <p class="hint" style="text-align:center;">Router admin-এ embed: <code>{{ $routerUrl }}</code></p>
</div>

@if ($customer !== null)
<script>
(function () {
    var form = document.getElementById('ai-form');
    var input = document.getElementById('ai-q');
    var log = document.getElementById('ai-log');
    if (!form || !input || !log) return;

    function addMsg(role, text) {
        var el = document.createElement('div');
        el.className = 'ai-msg ' + role;
        el.textContent = text;
        log.appendChild(el);
        log.scrollTop = log.scrollHeight;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        var q = (input.value || '').trim();
        if (!q) return;
        addMsg('user', q);
        input.value = '';
        fetch(@json(route('portal.router-home.ask')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ question: q })
        }).then(function (r) { return r.json(); }).then(function (data) {
            addMsg('bot', data.reply || '—');
        }).catch(function () {
            addMsg('bot', 'সাহায্য লোড হয়নি। পরে আবার চেষ্টা করুন।');
        });
    });
})();
</script>
@endif
</body>
</html>
