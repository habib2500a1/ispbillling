@php
    $vpnType = $vpnType ?? 'none';
    $hasOvpn = $hasOvpn ?? false;
    $hasPptp = $hasPptp ?? false;
    $egressIp = $egressIp ?? '—';
    $vpnPageUrl = $vpnPageUrl ?? '#';
@endphp
<div class="rounded-lg border border-info-200 bg-info-50 p-4 text-sm text-gray-800 dark:border-info-500/30 dark:bg-info-500/10 dark:text-gray-200">
    <p class="font-semibold text-info-800 dark:text-info-300">VPN ধাপ (OLT #{{ $oltId ?? '?' }})</p>
    <ol class="mt-2 list-decimal space-y-1.5 pl-5">
        <li><strong>VPN type</strong> = OpenVPN → নিচে <strong>পুরো .ovpn পেস্ট</strong> → <strong>Save</strong> (আপলোড বন্ধ — পেস্টই কাজ করে)</li>
        <li>পেজের উপরে ডান কোণে <strong>Test VPN now</strong> (২০–৬০ সেক) — অথবা <a href="{{ $vpnPageUrl }}" class="underline font-medium">OLT VPN / PPTP</a> মেনু থেকে Test</li>
        <li>নোটিফিকেশনে <strong>Recommended: openvpn</strong> হলে VPN type OpenVPN রেখে আবার Save</li>
        <li>তারপর <strong>Test Aveis connection</strong> বা <strong>Sync Aveis ONUs</strong></li>
    </ol>
    <p class="mt-3 text-xs text-gray-600 dark:text-gray-400">
        এখন: Active=<strong>{{ strtoupper($vpnType) }}</strong>,
        .ovpn=<strong>{{ $hasOvpn ? 'আছে' : 'নেই' }}</strong>,
        PPTP creds=<strong>{{ $hasPptp ? 'আছে' : 'নেই' }}</strong>.
        @if($vpnType === 'pptp' && ! $hasOvpn)
            <span class="text-warning-600 dark:text-warning-400"> habib.ovpn দেখতে VPN type OpenVPN করুন।</span>
        @endif
        MikroTik allow IP: <code class="rounded bg-white/80 px-1 dark:bg-gray-900">{{ $egressIp }}</code>
    </p>
</div>
