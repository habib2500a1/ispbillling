{{-- Full navigation for phones (sidebar is desktop-only). --}}
<div id="rsl-mobile-nav" class="rsl-mobile-nav" hidden aria-hidden="true">
    <button type="button" class="rsl-mobile-nav-backdrop" data-rsl-nav-close aria-label="Close menu"></button>
    <aside class="rsl-mobile-nav-panel" role="dialog" aria-modal="true" aria-labelledby="rsl-mobile-nav-title">
        <header class="rsl-mobile-nav-head">
            <div>
                <p id="rsl-mobile-nav-title" class="rsl-mobile-nav-title">{{ $reseller->brand_name ?: $reseller->name }}</p>
                <p class="rsl-mobile-nav-sub">{{ $reseller->code }}</p>
            </div>
            <button type="button" class="rsl-mobile-nav-close" data-rsl-nav-close aria-label="Close menu">
                <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" d="M6 6l12 12M18 6L6 18"/></svg>
            </button>
        </header>
        <nav class="rsl-mobile-nav-body">
            <p class="rsl-mobile-nav-label">Menu</p>
            @foreach ($navPrimary as [$route, $label, $patterns])
                <a href="{{ route($route) }}" class="rsl-mobile-nav-link {{ $navActive($patterns) ? 'rsl-mobile-nav-link--active' : '' }}">
                    @include('reseller.partials.nav-icons', ['name' => $navIcons[$route] ?? 'home'])
                    <span>{{ $label }}</span>
                </a>
            @endforeach
            @if (count($navMore) > 0)
                <p class="rsl-mobile-nav-label">More</p>
                @foreach ($navMore as [$route, $label, $patterns])
                    <a href="{{ route($route) }}" class="rsl-mobile-nav-link {{ $navActive($patterns) ? 'rsl-mobile-nav-link--active' : '' }}">
                        @include('reseller.partials.nav-icons', ['name' => $navIcons[$route] ?? 'hub'])
                        <span>{{ $label }}</span>
                    </a>
                @endforeach
            @endif
        </nav>
        <footer class="rsl-mobile-nav-foot">
            <form method="post" action="{{ route('reseller.logout') }}">
                @csrf
                <button type="submit" class="rsl-mobile-nav-logout">Log out</button>
            </form>
        </footer>
    </aside>
</div>
