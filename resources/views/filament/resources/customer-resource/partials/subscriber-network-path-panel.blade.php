@php
    $path = $networkPath ?? [];
    $mt = $path['mikrotik'] ?? [];
    $ppp = $path['ppp'] ?? [];
    $onu = $path['onu'] ?? [];
    $home = $path['home_router'] ?? [];
    $links = $path['links'] ?? [];
    $office = $path['office_access'] ?? [];
    $oneClick = $path['one_click_router'] ?? [];
@endphp

<section class="isp-cv-card sub-cc-panel sub-cc-panel--path">
    <div class="isp-cv-card__head">
        <h3 class="isp-cv-card__title">MikroTik → Router → ONU</h3>
        <button type="button" class="isp-cv-link" wire:click="syncNetworkPath" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="syncNetworkPath">Auto-detect</span>
            <span wire:loading wire:target="syncNetworkPath">Syncing…</span>
        </button>
    </div>

    @if ($oneClick['available'] ?? false)
        <div class="sub-path-oneclick mb-3">
            <a
                href="{{ $oneClick['url'] }}"
                class="sub-router-access__btn sub-router-access__btn--oneclick"
                target="_blank"
                rel="noopener"
                x-data="{
                    openRouterLogin() {
                        const pass = @js($oneClick['password'] ?? null);
                        const user = @js($oneClick['user'] ?? 'admin');
                        if (pass) {
                            navigator.clipboard.writeText(pass).catch(() => {});
                        }
                        window.open(@js($oneClick['url']), '_blank', 'noopener');
                        $wire.notifyRouterLoginReady(!!pass, user);
                    }
                }"
                @click.prevent="openRouterLogin()"
            >
                <x-filament::icon icon="heroicon-o-bolt" class="h-4 w-4" />
                Router login (1-click) — same MikroTik
            </a>
            <p class="text-xs text-emerald-700 dark:text-emerald-300 mt-2">{{ $oneClick['hint'] ?? '' }}</p>
        </div>
    @endif

    <p class="text-xs text-slate-500 mb-3 font-mono">{{ $path['path_label'] ?? '—' }}</p>

    <dl class="sub-cc-kv">
        <div><dt>MikroTik</dt><dd>{{ $mt['name'] ?? '—' }} <span class="font-mono text-xs">{{ $mt['host'] ?? '' }}</span></dd></div>
        <div><dt>PPPoE login</dt><dd class="font-mono">{{ $ppp['login'] ?? '—' }}</dd></div>
        <div><dt>WAN IP</dt><dd class="font-mono">{{ $ppp['framed_ip'] ?? '—' }}</dd></div>
        <div><dt>Router MAC</dt><dd class="font-mono text-xs">{{ $ppp['caller_id'] ?? '—' }}</dd></div>
        <div><dt>ONU</dt><dd>{{ ($onu['linked'] ?? false) ? ($onu['serial'] ?? 'Linked') : 'Not linked' }}</dd></div>
        <div><dt>EPON / RX</dt><dd>{{ $onu['epon'] ?? '—' }} · {{ isset($onu['rx_dbm']) ? $onu['rx_dbm'].' dBm' : '—' }}</dd></div>
    </dl>

    <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
        <h4 class="text-sm font-semibold mb-2">Office — same MikroTik network</h4>
        @if ($office['online'] ?? false)
            <p class="text-xs text-emerald-700 dark:text-emerald-300 mb-2">Subscriber online on this MikroTik — office/LAN থেকে WAN IP দিয়ে router admin try করা যায় (router-এ remote admin ON থাকলে)।</p>
            <dl class="sub-cc-kv">
                <div><dt>Live WAN (MT)</dt><dd class="font-mono">{{ $office['wan_ip'] ?? '—' }}</dd></div>
                @if (! empty($office['lan_ip']))
                    <div><dt>LAN (ARP/DHCP)</dt><dd class="font-mono">{{ $office['lan_ip'] }}</dd></div>
                @endif
            </dl>
            <div class="flex flex-wrap gap-2 mt-2">
                @if (! empty($office['wan_admin_url']))
                    <a href="{{ $office['wan_admin_url'] }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">Open WAN router</a>
                @endif
                @if (! empty($office['lan_admin_url']))
                    <a href="{{ $office['lan_admin_url'] }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">Open LAN IP</a>
                @endif
            </div>
        @else
            <p class="text-xs text-slate-500">PPP offline — online হলে MikroTik থেকে live WAN IP দেখাবে।</p>
        @endif
    </div>

    <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
        <h4 class="text-sm font-semibold mb-2">Home user login (billing)</h4>
        <p class="text-xs text-slate-500 mb-2">Customer WiFi থেকে বিল/AI — <code class="text-xs">{{ $links['billing_router_portal'] ?? '/router' }}</code></p>
        <a href="{{ $links['billing_router_portal'] ?? '#' }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">Home user portal (/router)</a>
        <a href="{{ $links['portal_token'] ?? '#' }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">Full portal token</a>
    </div>

    <div class="mt-3 pt-3 border-t border-slate-200 dark:border-slate-700">
        <h4 class="text-sm font-semibold mb-2">Home router (LAN — on-site)</h4>
        <dl class="sub-cc-kv">
            <div><dt>Admin URL</dt><dd><a href="{{ $home['lan_url'] ?? '#' }}" target="_blank" rel="noopener" class="isp-cv-link font-mono text-xs">{{ $home['lan_url'] ?? '—' }}</a></dd></div>
            <div><dt>User</dt><dd class="font-mono">{{ $home['user'] ?? 'admin' }}</dd></div>
            <div><dt>WiFi / admin pass</dt>
                <dd>
                    @if (! empty($home['password_set']))
                        <span class="isp-cv-password" x-data="{ show: false }">
                            <span class="font-mono text-sm" x-text="show ? @js($home['password'] ?? '') : '••••••'"></span>
                            <button type="button" class="isp-cv-password__toggle ml-1" @click="show = !show">👁</button>
                        </span>
                    @else
                        <span class="text-slate-500 text-sm">Not saved — Set home router login</span>
                    @endif
                </dd>
            </div>
        </dl>
        <p class="text-xs text-amber-700 dark:text-amber-300 mt-2">LAN admin (192.168.x) — customer বাড়িতে বা same WiFi। Office থেকে WAN IP কাজ করলে উপরের Open WAN ব্যবহার করুন।</p>
    </div>

    <div class="flex flex-wrap gap-2 mt-3">
        @if (! empty($mt['admin_url']))
            <a href="{{ $mt['admin_url'] }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">ISP MikroTik</a>
        @endif
    </div>
</section>
