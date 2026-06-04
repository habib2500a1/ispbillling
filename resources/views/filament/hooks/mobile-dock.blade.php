@auth
    @php($dock = \App\Support\MobileDockPresenter::data())
    <aside class="isp-mobile-bar isp-mobile-bar--color" aria-label="Mobile quick actions">
        <button
            type="button"
            class="isp-mobile-bar__search"
            title="Search subscribers"
            onclick="window.dispatchEvent(new CustomEvent('isp-open-command-palette'))"
        >
            <span class="isp-mobile-bar__search-icon" aria-hidden="true">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" width="20" height="20" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                </svg>
            </span>
            <span class="isp-mobile-bar__search-text">Search ID, name, phone…</span>
        </button>

        <nav class="isp-mobile-bar__nav" aria-label="Quick navigation">
            <a
                href="{{ $dock['dashboardUrl'] }}"
                class="isp-mobile-bar__chip isp-mobile-bar__chip--home {{ $dock['onDashboard'] ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-home" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Home</span>
            </a>
            <a
                href="{{ $dock['billingUrl'] }}"
                class="isp-mobile-bar__chip isp-mobile-bar__chip--collect {{ $dock['onBilling'] ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-currency-bangladeshi" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Collect</span>
            </a>
            <a
                href="{{ $dock['subscribersUrl'] }}"
                class="isp-mobile-bar__chip isp-mobile-bar__chip--users {{ $dock['onSubscribers'] ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Users</span>
            </a>
            @if ($dock['connectionsUrl'])
                <a
                    href="{{ $dock['connectionsUrl'] }}"
                    class="isp-mobile-bar__chip isp-mobile-bar__chip--leads {{ $dock['onConnections'] ? 'isp-mobile-bar__chip--active' : '' }}"
                    title="Portal new connection requests"
                >
                    <span class="isp-mobile-bar__chip-icon" style="position:relative">
                        <x-filament::icon icon="heroicon-o-user-plus" class="h-5 w-5" />
                        @if ($dock['newConnections'] > 0)
                            <span class="isp-mobile-bar__badge">{{ $dock['newConnections'] > 9 ? '9+' : $dock['newConnections'] }}</span>
                        @endif
                    </span>
                    <span class="isp-mobile-bar__chip-label">Leads</span>
                </a>
            @endif
            <a
                href="{{ $dock['smsUrl'] }}"
                class="isp-mobile-bar__chip isp-mobile-bar__chip--sms {{ $dock['onSms'] ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">SMS</span>
            </a>
            <a
                href="{{ $dock['networkUrl'] }}"
                class="isp-mobile-bar__chip isp-mobile-bar__chip--net {{ $dock['onNetwork'] ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-signal" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Net</span>
            </a>
            <button
                type="button"
                class="isp-mobile-bar__chip isp-mobile-bar__chip--menu"
                title="Full menu"
                x-data="{
                    syncSidebarBodyClass() {
                        const open = $store.sidebar.isOpen;
                        const desktop = window.matchMedia('(min-width: 1024px)').matches;
                        document.body.classList.toggle('isp-admin-sidebar-open', !desktop && open);
                        document.body.classList.toggle('isp-sidebar-desktop-collapsed', desktop && !open);
                        document.body.classList.toggle('isp-sidebar-desktop-expanded', desktop && open);
                    },
                }"
                x-init="syncSidebarBodyClass(); $watch('$store.sidebar.isOpen', () => syncSidebarBodyClass())"
                x-on:click.stop="
                    if ($store.sidebar.isOpen) {
                        $store.sidebar.close();
                    } else {
                        $store.sidebar.open();
                        window.dispatchEvent(new CustomEvent('isp-focus-sidebar-menu-search'));
                    }
                    syncSidebarBodyClass();
                "
                :class="{ 'isp-mobile-bar__chip--active': $store.sidebar.isOpen }"
                :aria-expanded="$store.sidebar.isOpen"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-bars-3" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Menu</span>
            </button>
        </nav>

        <div
            class="isp-mobile-bar__tools"
            x-data="{
                theme: window.ispGetTheme?.() || 'system',
                setTheme(mode) {
                    window.ispSetTheme?.(mode);
                    this.theme = mode;
                },
                cycleTheme() {
                    const order = ['light', 'dark', 'system'];
                    const i = Math.max(0, order.indexOf(this.theme));
                    this.setTheme(order[(i + 1) % order.length]);
                },
                themeLabel() {
                    return { light: 'Light', dark: 'Dark', system: 'Auto' }[this.theme] || 'Theme';
                },
            }"
            @isp-theme-changed.window="theme = $event.detail.mode"
        >
            <button
                type="button"
                class="isp-mobile-bar__pill isp-mobile-bar__pill--theme"
                @click.stop="cycleTheme()"
                :title="'Theme: ' + themeLabel()"
            >
                <span class="isp-mobile-bar__pill-icon">
                    <x-filament::icon icon="heroicon-m-sun" class="h-4 w-4" x-show="theme === 'light'" />
                    <x-filament::icon icon="heroicon-m-moon" class="h-4 w-4" x-show="theme === 'dark'" />
                    <x-filament::icon icon="heroicon-m-computer-desktop" class="h-4 w-4" x-show="theme === 'system'" />
                </span>
                <span x-text="themeLabel()"></span>
            </button>
        </div>
    </aside>
@endauth
