@props([
    'title',
    'subtitle' => null,
    'backUrl' => null,
    'backLabel' => '← Back',
    'actionUrl' => null,
    'actionLabel' => null,
])

<header class="rsl-page-head">
    <div class="rsl-page-head-main">
        @if ($backUrl)
            <a href="{{ $backUrl }}" class="rsl-page-back">{{ $backLabel }}</a>
        @endif
        <h1 class="rsl-page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="rsl-page-sub">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($actionUrl && $actionLabel)
        <div class="rsl-page-head-actions">
            <a href="{{ $actionUrl }}" class="rsl-btn-sm">{{ $actionLabel }}</a>
        </div>
    @endif
</header>
