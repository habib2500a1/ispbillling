<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('isp.company_name', 'ISP Platform') }}</title>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #020617;
            --panel: rgba(15, 23, 42, 0.78);
            --panel-soft: rgba(15, 23, 42, 0.58);
            --line: rgba(148, 163, 184, 0.18);
            --text: #e2e8f0;
            --muted: #94a3b8;
            --brand: #14b8a6;
            --brand-2: #8b5cf6;
            --ok: #34d399;
            --warn: #f59e0b;
        }

        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            background:
                radial-gradient(circle at top left, rgba(20, 184, 166, 0.16), transparent 32%),
                radial-gradient(circle at top right, rgba(139, 92, 246, 0.16), transparent 28%),
                linear-gradient(180deg, #020617 0%, #0f172a 100%);
            color: var(--text);
            min-height: 100vh;
        }

        a { color: inherit; text-decoration: none; }
        .shell { max-width: 1180px; margin: 0 auto; padding: 28px 20px 56px; }
        .topbar {
            display: flex; align-items: center; justify-content: space-between; gap: 16px;
            margin-bottom: 28px;
        }
        .brand { display: flex; flex-direction: column; gap: 6px; }
        .eyebrow {
            display: inline-flex; align-items: center; gap: 8px;
            font-size: 12px; font-weight: 800; letter-spacing: .12em; text-transform: uppercase;
            color: #67e8f9;
        }
        .eyebrow::before {
            content: ""; width: 8px; height: 8px; border-radius: 999px; background: linear-gradient(135deg, var(--brand), var(--brand-2));
            box-shadow: 0 0 0 6px rgba(20, 184, 166, 0.12);
        }
        .brand h1 { margin: 0; font-size: clamp(28px, 5vw, 54px); line-height: 1.02; letter-spacing: -0.04em; }
        .brand p { margin: 0; max-width: 760px; color: var(--muted); font-size: 16px; line-height: 1.7; }
        .nav { display: flex; flex-wrap: wrap; gap: 10px; }
        .pill {
            padding: 10px 14px; border-radius: 999px; border: 1px solid var(--line); background: rgba(255,255,255,0.04);
            color: var(--text); font-size: 13px; font-weight: 700;
        }
        .hero {
            display: grid; grid-template-columns: 1.35fr .95fr; gap: 22px; align-items: stretch;
            margin-bottom: 22px;
        }
        .panel {
            border: 1px solid var(--line); border-radius: 24px; background: var(--panel);
            backdrop-filter: blur(12px); box-shadow: 0 20px 60px rgba(2, 6, 23, 0.4);
        }
        .hero-copy { padding: 28px; }
        .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 22px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px;
            min-height: 46px; padding: 0 16px; border-radius: 14px; font-weight: 800; font-size: 14px;
            border: 1px solid transparent;
        }
        .btn-primary { background: linear-gradient(135deg, var(--brand), #0f766e); color: white; }
        .btn-secondary { background: rgba(255,255,255,0.06); border-color: var(--line); }
        .hero-meta {
            display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-top: 24px;
        }
        .stat {
            padding: 16px; border-radius: 18px; background: rgba(255,255,255,0.04); border: 1px solid rgba(148,163,184,.14);
        }
        .stat small { display: block; color: var(--muted); font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; }
        .stat strong { display: block; margin-top: 8px; font-size: 24px; letter-spacing: -.03em; }
        .hero-side { padding: 22px; display: grid; gap: 14px; }
        .mini-card {
            padding: 16px 18px; border-radius: 18px; border: 1px solid rgba(148,163,184,.14); background: rgba(255,255,255,0.04);
        }
        .mini-card h3 { margin: 0; font-size: 15px; }
        .mini-card p { margin: 8px 0 0; color: var(--muted); font-size: 13px; line-height: 1.6; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
        .card {
            padding: 20px; border-radius: 20px; border: 1px solid var(--line); background: var(--panel-soft);
            transition: transform .16s ease, border-color .16s ease, background .16s ease;
        }
        .card:hover { transform: translateY(-2px); border-color: rgba(20,184,166,.35); background: rgba(15,23,42,.78); }
        .card-label { font-size: 12px; font-weight: 800; text-transform: uppercase; letter-spacing: .08em; color: var(--muted); }
        .card-title { margin: 10px 0 0; font-size: 18px; font-weight: 800; }
        .card-desc { margin: 10px 0 0; color: var(--muted); font-size: 14px; line-height: 1.65; }
        .card-arrow { margin-top: 16px; display: inline-flex; color: #5eead4; font-weight: 800; }
        .split { display: grid; grid-template-columns: 1fr 1fr; gap: 18px; margin-top: 18px; }
        .list { padding: 22px; }
        .list h2 { margin: 0 0 12px; font-size: 20px; }
        .list ul { list-style: none; padding: 0; margin: 0; display: grid; gap: 12px; }
        .list li {
            padding: 14px 16px; border-radius: 16px; border: 1px solid rgba(148,163,184,.14); background: rgba(255,255,255,.04);
            color: var(--muted); font-size: 14px; line-height: 1.6;
        }
        .list b { color: var(--text); }
        .footer {
            margin-top: 22px; display: flex; flex-wrap: wrap; justify-content: space-between; gap: 12px;
            color: var(--muted); font-size: 13px;
        }
        .footer a { color: #67e8f9; }
        @media (max-width: 980px) {
            .hero, .split, .grid { grid-template-columns: 1fr; }
            .hero-meta { grid-template-columns: 1fr; }
            .topbar { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>
    <div class="shell">
        <header class="topbar">
            <div class="brand">
                <span class="eyebrow">ISP Billing SaaS</span>
                <h1>{{ config('isp.company_name', 'ISP Platform') }}</h1>
                <p>Multi-tenant ISP billing, customer portal, reseller operations, payments, network intelligence, GPON monitoring, and support automation in one production-ready platform.</p>
            </div>
            <nav class="nav">
                <a class="pill" href="{{ url('/admin/login') }}">Admin Login</a>
                <a class="pill" href="{{ url('/portal/login') }}">Customer Portal</a>
                <a class="pill" href="{{ url('/reseller/login') }}">Reseller Portal</a>
            </nav>
        </header>

        <section class="hero">
            <div class="panel hero-copy">
                <span class="eyebrow">Platform Access</span>
                <h2 style="margin:12px 0 0;font-size:clamp(24px,4vw,42px);line-height:1.08;letter-spacing:-0.04em;">Run billing, support, network, and collections from a single ISP workspace.</h2>
                <p style="margin:14px 0 0;color:var(--muted);font-size:15px;line-height:1.75;max-width:760px;">This deployment includes admin operations, customer self-service, reseller access, payment workflows, live usage visibility, notification tooling, and API surfaces for mobile or external integrations.</p>
                <div class="hero-actions">
                    <a class="btn btn-primary" href="{{ url('/admin/login') }}">Open Admin Panel</a>
                    <a class="btn btn-secondary" href="{{ url('/portal/login') }}">Open Customer Portal</a>
                    <a class="btn btn-secondary" href="{{ asset('docs/API_V1.md') }}">Read API Docs</a>
                </div>
                <div class="hero-meta">
                    <div class="stat">
                        <small>Architecture</small>
                        <strong>Multi-tenant</strong>
                    </div>
                    <div class="stat">
                        <small>Auth</small>
                        <strong>Portal + API</strong>
                    </div>
                    <div class="stat">
                        <small>Focus</small>
                        <strong>Billing + NOC</strong>
                    </div>
                </div>
            </div>
            <aside class="panel hero-side">
                <div class="mini-card">
                    <h3>Customer Self-Service</h3>
                    <p>Billing history, payments, tickets, live usage, ONU status, equipment, and knowledge base access.</p>
                </div>
                <div class="mini-card">
                    <h3>Admin Operations</h3>
                    <p>Clients, billing, support, network intelligence, reports, reseller control, backups, and security policies.</p>
                </div>
                <div class="mini-card">
                    <h3>Public Documentation</h3>
                    <p>Use the API reference to connect mobile apps, staff workflows, or partner integrations.</p>
                </div>
            </aside>
        </section>

        <section class="grid">
            <a class="card" href="{{ url('/admin/login') }}">
                <div class="card-label">Workspace</div>
                <div class="card-title">Admin Console</div>
                <div class="card-desc">Operations, billing, GPON, MikroTik, support, accounting, notifications, and staff security.</div>
                <span class="card-arrow">Enter admin →</span>
            </a>
            <a class="card" href="{{ url('/portal/login') }}">
                <div class="card-label">Self-Service</div>
                <div class="card-title">Customer Portal</div>
                <div class="card-desc">Invoices, online payment, tickets, usage monitoring, package changes, and portal notices.</div>
                <span class="card-arrow">Open portal →</span>
            </a>
            <a class="card" href="{{ url('/reseller/login') }}">
                <div class="card-label">Partner</div>
                <div class="card-title">Reseller Portal</div>
                <div class="card-desc">Subscriber stats, wallet balance, commissions, settlement awareness, and partner operations.</div>
                <span class="card-arrow">Open reseller →</span>
            </a>
            <a class="card" href="{{ asset('docs/API_V1.md') }}">
                <div class="card-label">Developers</div>
                <div class="card-title">API Reference</div>
                <div class="card-desc">Authentication, customer, staff, reseller, and mobile endpoints with current platform notes.</div>
                <span class="card-arrow">View docs →</span>
            </a>
        </section>

        <section class="split">
            <div class="panel list">
                <h2>Core Modules</h2>
                <ul>
                    <li><b>Billing:</b> invoice flows, due management, collection monitoring, payment gateway support.</li>
                    <li><b>Network:</b> online subscribers, MikroTik visibility, GPON/ONU monitoring, optical tools.</li>
                    <li><b>Support:</b> ticket operations, outage communication, notifications, and technician workflows.</li>
                </ul>
            </div>
            <div class="panel list">
                <h2>Operational Entry Points</h2>
                <ul>
                    <li><b>Admin:</b> <a href="{{ url('/admin/login') }}">{{ url('/admin/login') }}</a></li>
                    <li><b>Portal:</b> <a href="{{ url('/portal/login') }}">{{ url('/portal/login') }}</a></li>
                    <li><b>Reseller:</b> <a href="{{ url('/reseller/login') }}">{{ url('/reseller/login') }}</a></li>
                    <li><b>API docs:</b> <a href="{{ asset('docs/API_V1.md') }}">{{ asset('docs/API_V1.md') }}</a></li>
                </ul>
            </div>
        </section>

        <footer class="footer">
            <span>{{ config('isp.company_name', 'ISP Platform') }} · Laravel {{ Illuminate\Foundation\Application::VERSION }} · PHP {{ PHP_VERSION }}</span>
            <span><a href="{{ asset('docs/API_V1.md') }}">Public API docs</a></span>
        </footer>
    </div>
</body>
</html>
