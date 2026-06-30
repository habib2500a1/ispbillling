@php
    $path = $networkPath ?? [];
    $mt = $path['mikrotik'] ?? [];
    $ppp = $path['ppp'] ?? [];
    $onu = $path['onu'] ?? [];
    $home = $path['home_router'] ?? [];
    $links = $path['links'] ?? [];
@endphp

<section class="isp-cv-card sub-cc-panel sub-cc-panel--path">
    <div class="isp-cv-card__head">
        <h3 class="isp-cv-card__title">MikroTik → Router → ONU</h3>
        <button type="button" class="isp-cv-link" wire:click="syncNetworkPath" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="syncNetworkPath">Auto-detect</span>
            <span wire:loading wire:target="syncNetworkPath">Syncing…</span>
        </button>
    </div>

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
        <h4 class="text-sm font-semibold mb-2">Home router (LAN)</h4>
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
        <p class="text-xs text-amber-700 dark:text-amber-300 mt-2">LAN admin শুধু টেকনিশিয়ান on-site বা customer WiFi থেকে খুলুন। Remote WAN IP থেকে সাধারণত খোলা যায় না।</p>
    </div>

    <div class="flex flex-wrap gap-2 mt-3">
        @if (! empty($mt['admin_url']))
            <a href="{{ $mt['admin_url'] }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">ISP MikroTik</a>
        @endif
        <a href="{{ $links['billing_router_portal'] ?? '#' }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">Billing /router</a>
        <a href="{{ $links['portal_token'] ?? '#' }}" target="_blank" rel="noopener" class="sub-cc-nearby-chip">Portal token</a>
    </div>
</section>
