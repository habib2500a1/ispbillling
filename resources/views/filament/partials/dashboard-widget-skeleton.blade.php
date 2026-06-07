@php
    $variant = $variant ?? 'default';
    $height = $height ?? '12rem';
@endphp

<div
    class="isp-dash-skeleton isp-dash-skeleton--{{ $variant }}"
    style="min-height: {{ $height }};"
    aria-hidden="true"
    aria-label="Loading dashboard widget"
>
    <div class="isp-dash-skeleton__bar isp-skeleton"></div>
    @if ($variant === 'billing')
        <div class="isp-dash-skeleton__grid isp-dash-skeleton__grid--2">
            <div class="isp-skeleton isp-dash-skeleton__block"></div>
            <div class="isp-skeleton isp-dash-skeleton__block"></div>
        </div>
        <div class="isp-dash-skeleton__grid isp-dash-skeleton__grid--4">
            @for ($i = 0; $i < 4; $i++)
                <div class="isp-skeleton isp-dash-skeleton__kpi"></div>
            @endfor
        </div>
    @elseif ($variant === 'insights')
        <div class="isp-dash-skeleton__grid isp-dash-skeleton__grid--2">
            <div class="isp-skeleton isp-dash-skeleton__chart"></div>
            <div class="isp-skeleton isp-dash-skeleton__chart"></div>
        </div>
    @elseif ($variant === 'ops')
        <div class="isp-dash-skeleton__grid isp-dash-skeleton__grid--3">
            @for ($i = 0; $i < 6; $i++)
                <div class="isp-skeleton isp-dash-skeleton__kpi"></div>
            @endfor
        </div>
        <div class="isp-skeleton isp-dash-skeleton__block isp-dash-skeleton__block--tall"></div>
    @elseif ($variant === 'strip')
        <div class="isp-dash-skeleton__grid isp-dash-skeleton__grid--strip">
            @for ($i = 0; $i < 6; $i++)
                <div class="isp-skeleton isp-dash-skeleton__pill"></div>
            @endfor
        </div>
    @else
        <div class="isp-skeleton isp-dash-skeleton__block"></div>
    @endif
</div>
