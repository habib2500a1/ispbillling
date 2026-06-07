@if (\App\Support\DemoMode::enabled())
    <div
        class="isp-demo-credentials"
        style="margin:0 0 1rem;padding:0.75rem 1rem;border-radius:0.5rem;border:1px solid rgba(124,58,237,0.35);background:rgba(124,58,237,0.08);font-size:0.8rem;line-height:1.5;color:inherit;"
    >
        <strong style="display:block;margin-bottom:0.35rem;">ডেমো লগইন (fake data)</strong>
        @if (($demoHint ?? 'all') === 'customer' || ($demoHint ?? 'all') === 'all')
            <div>Customer: <code>DEMO-001</code> / <code>demo123</code></div>
        @endif
        @if (($demoHint ?? 'all') === 'reseller' || ($demoHint ?? 'all') === 'all')
            <div>Reseller: <code>DEMO-RSL</code> / <code>demo123</code></div>
        @endif
        @if (($demoHint ?? 'all') === 'admin' || ($demoHint ?? 'all') === 'all')
            <div>Admin: <code>demo@anetbd.com</code> (`.env` password)</div>
        @endif
        @if (($demoHint ?? 'all') === 'pay' || ($demoHint ?? 'all') === 'all')
            <div>Pay bill: client code <code>DEMO-001</code></div>
        @endif
    </div>
@endif
