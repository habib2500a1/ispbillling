@auth
    @php
        $onDashboard = request()->routeIs('filament.admin.pages.dashboard', 'filament.admin.pages.dashboard-hub');
        $onSubscribers = request()->routeIs(
            'filament.admin.pages.subscriber-lists-hub',
            'filament.admin.resources.subscribers.*',
        );
        $onBilling = request()->routeIs(
            'filament.admin.pages.bill-collection*',
            'filament.admin.pages.billing-overview',
            'filament.admin.pages.collector-mobile',
        );
        $onSms = request()->routeIs(
            'filament.admin.pages.sms-gateway',
            'filament.admin.pages.notifications-hub',
            'filament.admin.pages.bulk-sms-campaign',
            'filament.admin.resources.sms-delivery-reports.*',
            'filament.admin.resources.notification-logs.*',
        );
        $onNetwork = request()->routeIs(
            'filament.admin.pages.operations-hub',
            'filament.admin.pages.network-intelligence-hub',
            'filament.admin.pages.online-clients-monitoring',
            'filament.admin.pages.optical-monitoring-hub',
            'filament.admin.resources.mikrotik-servers.*',
        );
        $smsUrl = \App\Support\AdminNavUrl::for(\App\Filament\Pages\SmsGatewaySetup::class);
        $networkUrl = \App\Support\AdminNavUrl::for(\App\Filament\Pages\OperationsHub::class);
        $subscribersUrl = \App\Support\AdminNavUrl::for(\App\Filament\Pages\SubscriberListsHub::class);
        $currentLocale = app()->getLocale();
        $localeLabels = config('locales.labels', []);
    @endphp

    <aside
        class="isp-mobile-bar isp-mobile-bar--color"
        aria-label="Mobile quick actions"
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
            class="isp-mobile-bar__search"
            title="Search subscribers"
            @click.stop="window.dispatchEvent(new CustomEvent('isp-open-command-palette'))"
        >
            <span class="isp-mobile-bar__search-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-m-magnifying-glass" class="h-5 w-5" />
            </span>
            <span class="isp-mobile-bar__search-text">Search ID, name, phone…</span>
        </button>

        <nav class="isp-mobile-bar__nav" aria-label="Quick navigation">
            <a
                href="{{ \App\Filament\Pages\Dashboard::getUrl() }}"
                wire:navigate
                class="isp-mobile-bar__chip isp-mobile-bar__chip--home {{ $onDashboard ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-home" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Home</span>
            </a>
            <a
                href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}"
                wire:navigate
                class="isp-mobile-bar__chip isp-mobile-bar__chip--collect {{ $onBilling ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-currency-bangladeshi" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Collect</span>
            </a>
            <a
                href="{{ $subscribersUrl }}"
                wire:navigate
                class="isp-mobile-bar__chip isp-mobile-bar__chip--users {{ $onSubscribers ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-users" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Users</span>
            </a>
            <a
                href="{{ $smsUrl }}"
                wire:navigate
                class="isp-mobile-bar__chip isp-mobile-bar__chip--sms {{ $onSms ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-ellipsis" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">SMS</span>
            </a>
            <a
                href="{{ $networkUrl }}"
                wire:navigate
                class="isp-mobile-bar__chip isp-mobile-bar__chip--net {{ $onNetwork ? 'isp-mobile-bar__chip--active' : '' }}"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-signal" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Net</span>
            </a>
            <button
                type="button"
                class="isp-mobile-bar__chip isp-mobile-bar__chip--menu"
                x-on:click.stop="$store.sidebar.isOpen ? $store.sidebar.close() : $store.sidebar.open()"
                :class="{ 'isp-mobile-bar__chip--active': $store.sidebar.isOpen }"
                :aria-expanded="$store.sidebar.isOpen"
                title="Full menu"
            >
                <span class="isp-mobile-bar__chip-icon">
                    <x-filament::icon icon="heroicon-o-bars-3" class="h-5 w-5" />
                </span>
                <span class="isp-mobile-bar__chip-label">Menu</span>
            </button>
        </nav>

        <div class="isp-mobile-bar__tools">
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

            <div class="isp-mobile-bar__locales" role="group" aria-label="Language">
                @foreach (config('locales.supported', ['en']) as $code)
                    <a
                        href="{{ route('locale.switch', $code) }}"
                        class="isp-mobile-bar__locale {{ $currentLocale === $code ? 'isp-mobile-bar__locale--active' : '' }}"
                        title="{{ $localeLabels[$code] ?? $code }}"
                    >{{ strtoupper($code) }}</a>
                @endforeach
            </div>
        </div>
    </aside>
@endauth
