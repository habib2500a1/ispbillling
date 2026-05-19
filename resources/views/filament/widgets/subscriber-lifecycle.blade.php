@php $c = $this->getCounts(); @endphp

<x-filament-widgets::widget>
    <div class="isp-lifecycle-strip">
        <div class="mb-3 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Subscriber overview</h3>
                <p class="text-xs text-gray-500 dark:text-gray-400">Free = no auto bill · VIP / Free = no auto line off · Expired = date or status</p>
            </div>
            <a href="{{ $c['urls']['all'] }}" class="text-xs font-semibold text-teal-600 hover:underline dark:text-teal-400">All subscribers →</a>
        </div>

        <div class="isp-lifecycle-grid">
            <div class="isp-lifecycle-card isp-lifecycle-online">
                <span class="isp-lifecycle-label">Online now</span>
                <strong>{{ number_format($c['online']) }}</strong>
                <span class="isp-lifecycle-hint">PPP active</span>
            </div>
            <a href="{{ $c['urls']['all'] }}" class="isp-lifecycle-card">
                <span class="isp-lifecycle-label">Active</span>
                <strong>{{ number_format($c['active']) }}</strong>
            </a>
            <a href="{{ $c['urls']['free'] }}" class="isp-lifecycle-card isp-lifecycle-free">
                <span class="isp-lifecycle-label">Free</span>
                <strong>{{ number_format($c['free']) }}</strong>
                <span class="isp-lifecycle-hint">No bill</span>
            </a>
            <a href="{{ $c['urls']['vip'] }}" class="isp-lifecycle-card isp-lifecycle-vip">
                <span class="isp-lifecycle-label">VIP</span>
                <strong>{{ number_format($c['vip']) }}</strong>
                <span class="isp-lifecycle-hint">No auto off</span>
            </a>
            <a href="{{ $c['urls']['expired'] }}" class="isp-lifecycle-card isp-lifecycle-danger">
                <span class="isp-lifecycle-label">Expired</span>
                <strong>{{ number_format($c['expired']) }}</strong>
            </a>
            <a href="{{ $c['urls']['suspended'] }}" class="isp-lifecycle-card isp-lifecycle-warn">
                <span class="isp-lifecycle-label">Suspended</span>
                <strong>{{ number_format($c['suspended']) }}</strong>
            </a>
            <a href="{{ $c['urls']['left'] }}" class="isp-lifecycle-card">
                <span class="isp-lifecycle-label">Left</span>
                <strong>{{ number_format($c['left']) }}</strong>
            </a>
            <div class="isp-lifecycle-card isp-lifecycle-muted">
                <span class="isp-lifecycle-label">Expiring ≤7d</span>
                <strong>{{ number_format($c['expiring_soon']) }}</strong>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
