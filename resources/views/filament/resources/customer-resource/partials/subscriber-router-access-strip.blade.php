@php
    $path = $networkPath ?? [];
    $office = $path['office_access'] ?? [];
    $links = $path['links'] ?? [];
    $home = $path['home_router'] ?? [];
    $mt = $path['mikrotik'] ?? [];
    $ppp = $path['ppp'] ?? [];
    $oneClick = $path['one_click_router'] ?? [];
    $online = (bool) ($office['online'] ?? $ppp['online'] ?? false);
    $oneClickReady = (bool) ($oneClick['available'] ?? false);
@endphp

<section class="sub-router-access no-print" wire:key="router-access-{{ md5(json_encode([$office['wan_ip'] ?? '', $online, $oneClickReady])) }}">
    <div class="sub-router-access__head">
        <div>
            <h3 class="sub-router-access__title">Router &amp; portal login</h3>
            <p class="sub-router-access__hint">
                @if ($oneClickReady)
                    {{ $oneClick['hint'] ?? 'একই MikroTik — এক ক্লিকে router admin' }}
                    @if (! empty($oneClick['wan_ip']))
                        · <span class="font-mono">{{ $oneClick['wan_ip'] }}</span>
                    @endif
                @elseif ($online)
                    PPPoE online
                    @if (! empty($office['wan_ip']))
                        · WAN <span class="font-mono">{{ $office['wan_ip'] }}</span>
                    @endif
                @else
                    PPPoE offline — online হলে এক ক্লিক router login দেখাবে
                @endif
            </p>
        </div>
        <button type="button" class="isp-cv-link text-xs" wire:click="syncNetworkPath" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="syncNetworkPath">Auto-detect</span>
            <span wire:loading wire:target="syncNetworkPath">Syncing…</span>
        </button>
    </div>

    <div class="sub-router-access__actions">
        @if ($oneClickReady)
            <a
                href="{{ $oneClick['url'] }}"
                class="sub-router-access__btn sub-router-access__btn--oneclick"
                target="_blank"
                rel="noopener"
                x-data="{
                    openRouterLogin() {
                        const pass = @js($oneClick['password'] ?? null);
                        const user = @js($oneClick['user'] ?? 'admin');
                        let copied = false;
                        if (pass) {
                            navigator.clipboard.writeText(pass).then(() => { copied = true; }).catch(() => {});
                        }
                        window.open(@js($oneClick['url']), '_blank', 'noopener');
                        $wire.notifyRouterLoginReady(!!pass, user);
                    }
                }"
                @click.prevent="openRouterLogin()"
            >
                <x-filament::icon icon="heroicon-o-bolt" class="h-4 w-4" />
                Router login (1-click)
            </a>
        @endif

        <a href="{{ $portalLoginUrl ?? '#' }}" class="sub-router-access__btn sub-router-access__btn--primary" target="_blank" rel="noopener">
            <x-filament::icon icon="heroicon-o-arrow-right-on-rectangle" class="h-4 w-4" />
            Customer portal
        </a>

        @if (! empty($links['billing_router_portal']))
            <a href="{{ $links['billing_router_portal'] }}" class="sub-router-access__btn" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-home" class="h-4 w-4" />
                /router billing
            </a>
        @endif

        @if (! empty($links['portal_token']))
            <a href="{{ $links['portal_token'] }}" class="sub-router-access__btn" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-link" class="h-4 w-4" />
                Token link
            </a>
        @endif

        @if ($online && ! empty($office['wan_admin_url']) && ! $oneClickReady)
            <a href="{{ $office['wan_admin_url'] }}" class="sub-router-access__btn sub-router-access__btn--wan" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-globe-alt" class="h-4 w-4" />
                Open WAN router
            </a>
        @endif

        @if ($online && ! empty($office['lan_admin_url']) && ($oneClick['via'] ?? '') !== 'lan_arp')
            <a href="{{ $office['lan_admin_url'] }}" class="sub-router-access__btn" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-wifi" class="h-4 w-4" />
                LAN IP
            </a>
        @endif

        @if (! empty($home['lan_url']) && ! $oneClickReady)
            <a href="{{ $home['lan_url'] }}" class="sub-router-access__btn" target="_blank" rel="noopener" title="On-site LAN admin">
                <x-filament::icon icon="heroicon-o-cpu-chip" class="h-4 w-4" />
                Home LAN
            </a>
        @endif

        @if (! empty($mt['admin_url']))
            <a href="{{ $mt['admin_url'] }}" class="sub-router-access__btn" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-server" class="h-4 w-4" />
                MikroTik
            </a>
        @endif

        <button type="button" class="sub-router-access__btn sub-router-access__btn--ghost" wire:click="openHomeRouterLogin">
            <x-filament::icon icon="heroicon-o-key" class="h-4 w-4" />
            {{ ($home['password_set'] ?? false) ? 'Router pass' : 'Set router login' }}
        </button>
    </div>

    @if ($oneClickReady || ($home['password_set'] ?? false))
        <p class="sub-router-access__creds text-xs">
            @if ($oneClickReady)
                Router login:
                <span class="font-mono">{{ $oneClick['user'] ?? 'admin' }}</span>
                @if ($oneClick['password_set'] ?? false)
                    ·
                    <span class="isp-cv-password" x-data="{ show: false }">
                        <span class="font-mono" x-text="show ? @js($oneClick['password'] ?? '') : '••••••'"></span>
                        <button type="button" class="isp-cv-password__toggle ml-1" @click="show = !show">👁</button>
                    </span>
                @else
                    · <span class="text-amber-600">password সেভ করুন (Set router login)</span>
                @endif
            @elseif ($home['password_set'] ?? false)
                Home router:
                <span class="font-mono">{{ $home['user'] ?? 'admin' }}</span>
                ·
                <span class="isp-cv-password" x-data="{ show: false }">
                    <span class="font-mono" x-text="show ? @js($home['password'] ?? '') : '••••••'"></span>
                    <button type="button" class="isp-cv-password__toggle ml-1" @click="show = !show">👁</button>
                </span>
            @endif
        </p>
    @endif
</section>
