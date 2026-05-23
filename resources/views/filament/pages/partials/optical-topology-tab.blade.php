@php
    $topology = $topology ?? ['summary' => [], 'olts' => []];
    $summary = $topology['summary'] ?? [];
@endphp

<div class="space-y-4">
    <div class="grid gap-3 sm:grid-cols-4">
        @foreach ([
            ['OLTs', $summary['olts'] ?? 0, ''],
            ['ONUs', $summary['onus'] ?? 0, ''],
            ['Weak', $summary['weak_onus'] ?? 0, 'text-amber-600'],
            ['Offline', $summary['offline_onus'] ?? 0, 'text-rose-600'],
        ] as [$label, $value, $color])
            <div class="isp-optical-noc__kpi dark:bg-gray-900/80">
                <p class="text-[10px] font-bold uppercase text-gray-500">{{ $label }}</p>
                <p class="isp-optical-noc__kpi-value {{ $color }}">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-500">
        <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span> healthy PON
        <span class="inline-block h-2 w-2 rounded-full bg-amber-400 ml-3"></span> degraded
        <span class="inline-block h-2 w-2 rounded-full bg-rose-500 ml-3"></span> critical / down
    </p>

    @forelse ($topology['olts'] ?? [] as $olt)
        <div class="isp-topology-olt rounded-2xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <p class="text-lg font-bold text-violet-700 dark:text-violet-300">{{ $olt['label'] }}</p>
                    <p class="text-xs text-gray-500">{{ $olt['management_ip'] ?? '—' }} · {{ $olt['onu_online'] }}/{{ $olt['onu_total'] }} ONU online
                        @if(isset($olt['health_score'])) · health {{ $olt['health_score'] }}% @endif
                    </p>
                </div>
                @if(isset($olt['cpu_percent']))
                    <span class="text-xs rounded-full bg-cyan-100 px-2 py-0.5 text-cyan-900">CPU {{ $olt['cpu_percent'] }}%</span>
                @endif
            </div>

            <div class="mt-4 flex flex-col items-center">
                <div class="isp-topology-node isp-topology-node--olt">OLT</div>
                <div class="isp-topology-vline"></div>
            </div>

            @if(count($olt['ports'] ?? []) === 0)
                <p class="text-center text-sm text-gray-500 py-4">No PON ports — assign ports or run Huawei/BDCOM SNMP sync.</p>
            @else
                <div class="mt-2 grid gap-3 lg:grid-cols-2 xl:grid-cols-3">
                    @foreach ($olt['ports'] as $port)
                        @php
                            $pathClass = match ($port['path_health'] ?? 'healthy') {
                                'critical', 'down' => 'isp-topology-port--crit',
                                'degraded' => 'isp-topology-port--warn',
                                default => 'isp-topology-port--ok',
                            };
                        @endphp
                        <div class="isp-topology-port {{ $pathClass }} rounded-xl border p-3">
                            <div class="flex justify-between text-sm font-semibold">
                                <span>PON {{ $port['label'] }}</span>
                                <span class="text-xs text-gray-500">{{ $port['onu_online'] }}/{{ $port['onu_total'] }}</span>
                            </div>
                            @if(($port['onu_weak'] ?? 0) > 0 || ($port['onu_critical'] ?? 0) > 0)
                                <p class="mt-1 text-xs text-amber-700">{{ $port['onu_weak'] }} weak · {{ $port['onu_critical'] }} critical</p>
                            @endif
                            <ul class="mt-2 space-y-1 max-h-40 overflow-y-auto">
                                @foreach ($port['onus']['items'] ?? [] as $onu)
                                    @php
                                        $dot = match ($onu['signal_level'] ?? '') {
                                            'critical', 'offline' => 'bg-rose-500',
                                            'warning', 'high' => 'bg-amber-400',
                                            'excellent', 'good' => 'bg-emerald-500',
                                            default => 'bg-gray-400',
                                        };
                                    @endphp
                                    <li class="flex items-center gap-2 text-xs">
                                        <span class="h-2 w-2 shrink-0 rounded-full {{ $dot }}"></span>
                                        <span class="font-mono truncate">{{ $onu['serial'] }}</span>
                                        @if($onu['rx_dbm'] !== null)
                                            <span class="text-gray-500">{{ number_format((float)$onu['rx_dbm'], 1) }} dBm</span>
                                        @endif
                                    </li>
                                @endforeach
                                @if($port['onus']['truncated'] ?? false)
                                    <li class="text-xs text-amber-600">+ {{ ($port['onus']['total'] ?? 0) - count($port['onus']['items'] ?? []) }} more</li>
                                @endif
                            </ul>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @empty
        <p class="rounded-xl border border-dashed p-8 text-center text-gray-500">
            No OLT yet — Optical NOC header থেকে <strong>Add OLT</strong> দিন, তারপর Poll OLT health।
        </p>
    @endforelse

    <p class="text-xs text-gray-500">
        Full map: <a href="{{ \App\Filament\Pages\NetworkTopology::getUrl() }}" class="text-cyan-600 font-semibold hover:underline">Network topology</a>
    </p>
</div>
