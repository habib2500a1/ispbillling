@php
    $snapshot = $snapshot ?? ['linked' => false, 'rows' => [], 'hint' => null];
    $rows = $snapshot['rows'] ?? [];
    $onuBilling = $snapshot['onu_billing'] ?? [];
@endphp

<div class="mb-4 grid gap-3 md:grid-cols-2">
    <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm dark:border-slate-700 dark:bg-slate-900/40">
        <p class="text-xs font-bold uppercase tracking-wide text-slate-500">ONU lease / device (local + ISP Digital)</p>
        <dl class="mt-2 grid gap-1 text-sm">
            @foreach ($onuBilling as $label => $value)
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-600 dark:text-slate-400">{{ $label }}</dt>
                    <dd class="font-mono text-xs font-semibold">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
        <p class="mt-2 text-xs text-slate-500">
            ভাড়া/ডিপোজিট Edit client → Fees ট্যাবে। ISP Digital-এ খালি থাকলে header-এ «ISP Digital → Network/ONU» চাপুন।
        </p>
    </div>
    <div class="rounded-lg border border-cyan-200 bg-cyan-50/80 px-4 py-3 text-sm text-cyan-950 dark:border-cyan-900/50 dark:bg-cyan-950/20 dark:text-cyan-100">
        <p class="font-semibold">OLT থেকে optical power কীভাবে আসে</p>
        <ol class="mt-2 list-decimal space-y-1 pl-4 text-xs">
            <li>BDCOM OLT sync (inventory) — Optical NOC বা «Sync OLT & link ONU»</li>
            <li>ONU description = PPP login (যেমন <span class="font-mono">{{ $rows[0]['username'] ?? ($snapshot['ppp_login'] ?? '—') }}</span>)</li>
            <li>অথবা MikroTik secret comment-এ EPON0/4:29 বা ONU MAC</li>
            <li>Router MAC (PPPoE caller-id) ≠ ONU MAC — OLT MAC table ব্যবহার করুন</li>
        </ol>
    </div>
</div>

@if (! ($snapshot['linked'] ?? false))
    <div class="space-y-3">
        <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
            <p>{{ $snapshot['hint'] ?? 'No ONU linked.' }}</p>
            @if (config('optical.isp_digital_auto_sync'))
                <p class="mt-2 text-xs opacity-90">ISP Digital mode: OLT থেকে ONU auto আনছে। ১–২ মিনিট পর refresh করুন বা header-এ «Sync OLT & link ONU» চাপুন।</p>
            @endif
        </div>
        @if (! empty($snapshot['suggestions']))
            <div class="rounded-lg border border-blue-200 bg-blue-50/80 px-4 py-3 dark:border-blue-900/50 dark:bg-blue-950/20">
                <p class="text-xs font-bold uppercase text-blue-800 dark:text-blue-200">Suggested ONU (OLT inventory)</p>
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($snapshot['suggestions'] as $s)
                        <li class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-100 pb-2 last:border-0 dark:border-blue-900/40">
                            <span class="font-mono text-xs">{{ $s['label'] }}</span>
                            <span class="text-xs text-blue-700 dark:text-blue-300">{{ $s['reason'] }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
@else
    <div class="mb-2 flex flex-wrap gap-2">
        @if (! empty($optical_noc_url))
            <a href="{{ $optical_noc_url }}" class="isp-cd-btn isp-cd-btn--ghost text-xs">Optical NOC</a>
        @endif
        @if (! empty($laser_settings_url))
            <a href="{{ $laser_settings_url }}" class="isp-cd-btn isp-cd-btn--ghost text-xs">Laser thresholds</a>
        @endif
    </div>
    <div class="isp-optical-power-wrap overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="isp-optical-power-table min-w-full text-left text-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Client Code</th>
                    <th>UserName</th>
                    <th>Client Name</th>
                    <th>MacAddress</th>
                    <th>IpAddress</th>
                    <th>OLTName</th>
                    <th class="isp-optical-power-col">OpticalPower</th>
                    <th>TX (dBm)</th>
                    <th>OnuMacaddress</th>
                    <th>OLTPort</th>
                    <th>OnuStatus</th>
                    <th>Description</th>
                    <th>LastDeregisterTime</th>
                    <th>Distance</th>
                    <th>DeregisterReason</th>
                    <th>Last Synced Time</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr @class(['isp-optical-power-row--high' => ! empty($row['is_high_laser'])])>
                        <td>{{ $row['index'] }}</td>
                        <td class="font-mono text-xs">{{ $row['client_code'] }}</td>
                        <td class="font-mono text-xs">{{ $row['username'] }}</td>
                        <td>{{ $row['client_name'] }}</td>
                        <td class="font-mono text-xs whitespace-nowrap">{{ $row['mac_address'] }}</td>
                        <td class="font-mono text-xs whitespace-nowrap">{{ $row['ip_address'] }}</td>
                        <td>{{ $row['olt_name'] }}</td>
                        <td class="isp-optical-power-col">
                            <span @class([
                                'isp-optical-power-value',
                                'isp-optical-power-value--' . ($row['optical_color'] ?? 'gray'),
                            ])>
                                {{ $row['optical_power'] }}
                            </span>
                            @if (($row['optical_power_raw'] ?? null) !== null)
                                <span class="block text-[10px] font-medium opacity-80">{{ $row['optical_level_label'] }}</span>
                            @endif
                        </td>
                        <td class="font-mono text-xs tabular-nums">{{ $row['tx_power'] }}</td>
                        <td class="font-mono text-xs whitespace-nowrap">{{ $row['onu_mac'] }}</td>
                        <td class="font-mono text-xs whitespace-nowrap">{{ $row['olt_port'] }}</td>
                        <td>
                            <span @class([
                                'isp-optical-status',
                                'isp-optical-status--online' => strtolower($row['onu_status']) === 'online',
                                'isp-optical-status--offline' => strtolower($row['onu_status']) !== 'online',
                            ])>{{ $row['onu_status'] }}</span>
                        </td>
                        <td class="max-w-[8rem] truncate" title="{{ $row['description'] }}">{{ $row['description'] }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $row['last_deregister_time'] }}</td>
                        <td class="tabular-nums">{{ $row['distance'] }}</td>
                        <td class="text-xs">{{ $row['deregister_reason'] }}</td>
                        <td class="text-xs whitespace-nowrap">{{ $row['last_synced_time'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">OpticalPower = RX dBm · Auto-refresh every 60 seconds</p>
@endif
