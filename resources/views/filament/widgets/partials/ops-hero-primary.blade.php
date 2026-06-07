<header class="isp-cmd-hero isp-cmd-hero--pro isp-cmd-hero--v2">
    <div class="isp-cmd-hero__main">
        <div class="isp-cmd-hero__live">
            <span class="isp-live-dot" aria-hidden="true"></span>
            Operations command center
            <span class="isp-cmd-hero__sep">·</span>
            {{ $ops['company'] ?? config('app.name') }}
        </div>
        <h2 class="isp-cmd-hero__title">Subscribers, billing, network ও support</h2>
        <p class="isp-cmd-hero__lead">
            Real-time KPIs, activity feeds, and operational shortcuts for your team.
        </p>
    </div>
    <div class="isp-cmd-hero__chips">
        @foreach ($highlights as $chip)
            @if (! empty($chip['url']))
                <a href="{{ $chip['url'] }}" class="isp-cmd-chip">{{ $chip['label'] }}: <strong>{{ $chip['value'] }}</strong></a>
            @else
                <span class="isp-cmd-chip">{{ $chip['label'] }}: <strong>{{ $chip['value'] }}</strong></span>
            @endif
        @endforeach
        @if ($updated)
            <span class="isp-cmd-chip isp-cmd-chip--muted">{{ \Carbon\Carbon::parse($updated)->diffForHumans() }}</span>
        @endif
    </div>
</header>

@if (($mfsPending['count'] ?? 0) > 0)
    <div class="isp-mfs-pending-alert" role="alert">
        <div class="isp-mfs-pending-alert__main">
            <strong>{{ $mfsPending['count'] }} টি MFS পেমেন্ট যাচাই বাকি</strong>
            <p>ক্লায়েন্ট ভুল TrxID দিয়েছে বা SMS মিলেনি — Pending gateway payments থেকে Approve করুন।</p>
        </div>
        @if (! empty($mfsPending['url']))
            <a href="{{ $mfsPending['url'] }}" class="isp-mfs-pending-alert__cta">Verify &amp; approve →</a>
        @endif
    </div>
@endif

<div class="isp-cmd-primary isp-cmd-primary--v2">
    @foreach ($primary as $kpi)
        <a
            href="{{ $kpi['url'] ?? '#' }}"
            @class([
                'isp-cmd-primary__card',
                'isp-cmd-primary__card--' . ($kpi['tone'] ?? 'teal'),
                'isp-cmd-primary__card--static' => empty($kpi['url']),
            ])
        >
            <span class="isp-cmd-primary__label">{{ $kpi['label'] }}</span>
            <strong class="isp-cmd-primary__value">{{ $kpi['value'] }}</strong>
            <span class="isp-cmd-primary__hint">{{ $kpi['hint'] }}</span>
        </a>
    @endforeach
</div>
