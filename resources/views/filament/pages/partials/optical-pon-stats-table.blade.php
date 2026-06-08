@php
    try {
        $ponPorts = $this->getPonPortsPayload();
    } catch (\Throwable) {
        $ponPorts = [];
    }
    $withVlan = collect($ponPorts)->filter(fn (array $p): bool => ($p['vlan'] ?? '—') !== '—')->count();
    $withMk = collect($ponPorts)->filter(fn (array $p): bool => ($p['mikrotik'] ?? '—') !== '—')->count();
@endphp

<section id="isp-pon-stats" class="isp-optical-noc__chart-card isp-pon-stats-panel space-y-3 text-sm" x-data="{ ponFilter: 'all' }">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-gray-900 dark:text-white">PON ports — MikroTik &amp; VLAN</h3>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                প্রতি OLT-তে ৮টি PON (auto) · MikroTik/VLAN = subscriber লিংক থাকলে · খালি/অফলাইন পোর্ট আলাদা দেখায়
            </p>
        </div>
        <div class="flex flex-wrap gap-2 text-xs font-semibold">
            <span class="rounded-full bg-violet-500/15 px-2.5 py-1 text-violet-700 dark:text-violet-300">{{ count($ponPorts) }} PON</span>
            <span class="rounded-full bg-cyan-500/15 px-2.5 py-1 text-cyan-800 dark:text-cyan-200">{{ $withMk }} MikroTik</span>
            <span class="rounded-full bg-emerald-500/15 px-2.5 py-1 text-emerald-800 dark:text-emerald-200">{{ $withVlan }} VLAN</span>
        </div>
    </div>

    <nav class="olt-oc-tabs" aria-label="PON filters" style="margin:0;">
        @foreach (['all' => 'All ports', 'online' => 'Active', 'partial' => 'Degraded', 'offline' => 'Offline', 'weak' => 'Weak signal'] as $key => $label)
            <button type="button" @click="ponFilter = '{{ $key }}'" @class(['olt-oc-tab', 'olt-oc-tab--active' => false]) :class="ponFilter === '{{ $key }}' ? 'olt-oc-tab olt-oc-tab--active' : 'olt-oc-tab'">{{ $label }}</button>
        @endforeach
    </nav>

    <p class="isp-pon-stats-scroll-hint text-xs font-medium text-amber-700 dark:text-amber-300">
        টেবিলে MikroTik ও VLAN বাম দিকে — ছোট স্ক্রিনে নিচের দিকে স্ক্রল করুন।
    </p>

    <div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
        <table class="isp-pon-stats-table w-full min-w-[48rem] text-left">
            <thead>
                <tr class="border-b bg-slate-800 text-xs uppercase tracking-wide text-white">
                    <th class="isp-pon-stats-table__sticky px-3 py-2.5">OLT</th>
                    <th class="px-3 py-2.5">Status</th>
                    <th class="isp-pon-stats-table__sticky px-3 py-2.5">MikroTik</th>
                    <th class="isp-pon-stats-table__sticky px-3 py-2.5">VLAN</th>
                    <th class="px-3 py-2.5">PON port name</th>
                    <th class="px-3 py-2.5">Index</th>
                    <th class="px-3 py-2.5">ONUs</th>
                    <th class="px-3 py-2.5">Weak</th>
                    <th class="px-3 py-2.5">Avg RX</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($ponPorts as $pon)
                    @php
                        $lineStatus = $pon['line_status'] ?? 'online';
                        $rowClass = match ($lineStatus) {
                            'empty' => 'bg-slate-500/10 opacity-75',
                            'offline' => 'bg-rose-500/10',
                            'partial' => 'bg-amber-500/5',
                            default => '',
                        };
                        $statusClass = match ($lineStatus) {
                            'empty' => 'bg-slate-500/20 text-slate-600 dark:text-slate-300',
                            'offline' => 'bg-rose-500/20 text-rose-800 dark:text-rose-200',
                            'partial' => 'bg-amber-500/20 text-amber-900 dark:text-amber-200',
                            default => 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-200',
                        };
                        $weakCount = ($pon['onu_warning'] ?? 0) + ($pon['onu_critical'] ?? 0);
                    @endphp
                    <tr
                        @class(['border-b border-gray-100 dark:border-gray-800', $rowClass])
                        title="{{ $pon['mikrotik_detail'] ?? '' }} · {{ $pon['vlan_detail'] ?? '' }}"
                        x-show="ponFilter === 'all'
                            || (ponFilter === '{{ $lineStatus }}' && ponFilter !== 'weak')
                            || (ponFilter === 'weak' && {{ $weakCount }} > 0)"
                        x-cloak
                    >
                        <td class="isp-pon-stats-table__sticky px-3 py-2 font-medium">{{ $pon['olt_name'] }}</td>
                        <td class="px-3 py-2">
                            <span class="whitespace-nowrap rounded px-1.5 py-0.5 text-[10px] font-bold uppercase {{ $statusClass }}">
                                {{ $pon['line_status_label'] ?? '—' }}
                            </span>
                        </td>
                        <td class="isp-pon-stats-table__sticky px-3 py-2">
                            @if (($pon['mikrotik'] ?? '—') !== '—')
                                <span class="font-semibold text-cyan-700 dark:text-cyan-300">{{ $pon['mikrotik'] }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="isp-pon-stats-table__sticky px-3 py-2" title="{{ $pon['vlan_detail'] ?? '' }}">
                            @if (($pon['vlan'] ?? '—') !== '—')
                                <span class="whitespace-nowrap rounded bg-emerald-500/15 px-1.5 py-0.5 font-mono text-xs font-bold text-emerald-800 dark:text-emerald-200">{{ $pon['vlan'] }}</span>
                            @else
                                <span class="text-gray-400">—</span>
                            @endif
                        </td>
                        <td class="px-3 py-2">{{ $pon['port_name'] }}</td>
                        <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $pon['port_index'] }}</td>
                        <td class="px-3 py-2 tabular-nums">{{ $pon['onu_online'] }}/{{ $pon['onu_total'] }}</td>
                        <td class="px-3 py-2 tabular-nums text-amber-600">{{ ($pon['onu_warning'] ?? 0) + ($pon['onu_critical'] ?? 0) }}</td>
                        <td class="px-3 py-2 font-mono text-xs tabular-nums">{{ $pon['avg_rx_dbm'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-3 py-8 text-center text-gray-500">
                            No PON stats — run <strong>Poll OLT</strong> or optical sync.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
