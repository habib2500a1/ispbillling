@php
    $path = $networkPath ?? [];
    $office = $path['office_access'] ?? [];
    $links = $path['links'] ?? [];
    $home = $path['home_router'] ?? [];
    $mt = $path['mikrotik'] ?? [];
    $ppp = $path['ppp'] ?? [];
    $online = (bool) ($office['online'] ?? $ppp['online'] ?? false);
@endphp

<section class="sub-router-access no-print" wire:key="router-access-{{ md5(json_encode([$office['wan_ip'] ?? '', $online])) }}">
    <div class="sub-router-access__head">
        <div>
            <h3 class="sub-router-access__title">Router &amp; portal login</h3>
            <p class="sub-router-access__hint">
                @if ($online)
                    PPPoE online
                    @if (! empty($office['wan_ip']))
                        · WAN <span class="font-mono">{{ $office['wan_ip'] }}</span>
                    @endif
                @else
                    PPPoE offline — WAN login online হলে দেখাবে
                @endif
            </p>
        </div>
        <button type="button" class="isp-cv-link text-xs" wire:click="syncNetworkPath" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="syncNetworkPath">Auto-detect</span>
            <span wire:loading wire:target="syncNetworkPath">Syncing…</span>
        </button>
    </div>

    <div class="sub-router-access__actions">
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

        @if ($online && ! empty($office['wan_admin_url']))
            <a href="{{ $office['wan_admin_url'] }}" class="sub-router-access__btn sub-router-access__btn--wan" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-globe-alt" class="h-4 w-4" />
                Open WAN router
            </a>
        @endif

        @if ($online && ! empty($office['lan_admin_url']))
            <a href="{{ $office['lan_admin_url'] }}" class="sub-router-access__btn" target="_blank" rel="noopener">
                <x-filament::icon icon="heroicon-o-wifi" class="h-4 w-4" />
                LAN IP
            </a>
        @endif

        @if (! empty($home['lan_url']))
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

    @if ($home['password_set'] ?? false)
        <p class="sub-router-access__creds text-xs">
            Home router:
            <span class="font-mono">{{ $home['user'] ?? 'admin' }}</span>
            ·
            <span class="isp-cv-password" x-data="{ show: false }">
                <span class="font-mono" x-text="show ? @js($home['password'] ?? '') : '••••••'"></span>
                <button type="button" class="isp-cv-password__toggle ml-1" @click="show = !show">👁</button>
            </span>
            @if (! empty($home['lan_url']))
                · <a href="{{ $home['lan_url'] }}" target="_blank" rel="noopener" class="isp-cv-link font-mono">{{ parse_url($home['lan_url'], PHP_URL_HOST) ?: $home['lan_url'] }}</a>
            @endif
        </p>
    @endif
</section>
