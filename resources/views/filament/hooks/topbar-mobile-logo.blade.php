@if (filament()->hasNavigation())
    <div class="isp-topbar-mobile-logo lg:hidden">
        @if ($homeUrl = filament()->getHomeUrl())
            <a
                href="{{ $homeUrl }}"
                class="isp-topbar-mobile-logo__link"
                aria-label="{{ filament()->getBrandName() }}"
            >
                <x-filament-panels::logo />
            </a>
        @else
            <a
                href="{{ filament()->getUrl() }}"
                class="isp-topbar-mobile-logo__link"
                aria-label="{{ filament()->getBrandName() }}"
            >
                <x-filament-panels::logo />
            </a>
        @endif
    </div>
@endif
