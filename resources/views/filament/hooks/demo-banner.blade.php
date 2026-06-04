@if (\App\Support\DemoMode::enabled() && auth()->check() && ! request()->routeIs('filament.admin.auth.*'))
    <div
        class="isp-demo-banner"
        role="status"
        style="position:sticky;top:0;z-index:60;width:100%;padding:0.4rem 1rem;text-align:center;font-size:0.75rem;font-weight:700;letter-spacing:0.06em;background:linear-gradient(90deg,#7c3aed,#db2777);color:#fff;box-shadow:0 2px 8px rgba(0,0,0,0.15);"
    >
        {{ \App\Support\DemoMode::label() }} — {{ \App\Support\DemoMode::message() }}
    </div>
@endif
