@php
    $topology = $this->getTopology();
    $summary = $topology['summary'];
@endphp

<x-filament-panels::page>
    <div
        class="space-y-6"
        x-data="{ tab: @entangle('activeTab') }"
    >
        <div class="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-cyan-50/40 p-6 shadow-sm dark:border-violet-900/40 dark:from-violet-950/40 dark:via-gray-900 dark:to-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Network topology map</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Live hierarchy: MikroTik routers → OLT → PON ports → ONUs → subscribers, plus geographic areas.
            </p>
            <div class="mt-4 flex flex-wrap gap-2 text-sm">
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">{{ $summary['mikrotik'] }} MikroTik</span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">{{ $summary['olts'] }} OLT</span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">{{ $summary['onus_online'] }}/{{ $summary['onus'] }} ONU online</span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">{{ $summary['areas'] }} areas</span>
                <span class="rounded-full bg-white px-3 py-1 font-medium shadow-sm dark:bg-gray-800">{{ $summary['customers'] }} subscribers</span>
            </div>
        </div>

        <div class="flex flex-wrap gap-2">
            <button
                type="button"
                @click="tab = 'fiber'"
                :class="tab === 'fiber' ? 'bg-violet-600 text-white' : 'bg-white text-gray-700 dark:bg-gray-800 dark:text-gray-200'"
                class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm ring-1 ring-gray-200 dark:ring-gray-700"
            >
                Fiber & access
            </button>
            <button
                type="button"
                @click="tab = 'geo'"
                :class="tab === 'geo' ? 'bg-cyan-600 text-white' : 'bg-white text-gray-700 dark:bg-gray-800 dark:text-gray-200'"
                class="rounded-lg px-4 py-2 text-sm font-semibold shadow-sm ring-1 ring-gray-200 dark:ring-gray-700"
            >
                Geographic tree
            </button>
        </div>

        <div x-show="tab === 'fiber'" x-cloak class="space-y-8">
            <section>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500">Core — MikroTik</h3>
                @if (count($topology['mikrotik']) === 0)
                    <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-600">No MikroTik servers configured.</p>
                @else
                    <div class="ntree-root flex flex-wrap justify-center gap-4">
                        @foreach ($topology['mikrotik'] as $mt)
                            <a href="{{ $mt['edit_url'] }}" class="ntree-node ntree-node--core group">
                                <span class="ntree-dot {{ $mt['api_ok'] ? 'ntree-dot--ok' : 'ntree-dot--warn' }}"></span>
                                <p class="font-bold text-gray-900 group-hover:text-violet-600 dark:text-white">{{ $mt['name'] }}</p>
                                <p class="text-xs text-gray-500">{{ $mt['host'] }}</p>
                                <p class="mt-2 text-xs font-semibold text-violet-700">{{ $mt['online'] }} online / {{ $mt['customers'] }} PPPoE</p>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="ntree-connector mx-auto h-8 w-px bg-gradient-to-b from-violet-400 to-cyan-400"></div>

            <section>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500">Access — OLT → PON → ONU</h3>
                @if (count($topology['olts']) === 0)
                    <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-600">No OLTs in inventory. Add devices from ONU / OLT integration.</p>
                @else
                    <div class="space-y-8">
                        @foreach ($topology['olts'] as $olt)
                            <div class="ntree-olt rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <a href="{{ $olt['edit_url'] }}" class="text-lg font-bold text-violet-700 hover:underline dark:text-violet-300">{{ $olt['label'] }}</a>
                                        <p class="text-xs text-gray-500">{{ $olt['management_ip'] ?? 'No management IP' }} · {{ $olt['onu_online'] }}/{{ $olt['onu_total'] }} ONU online</p>
                                    </div>
                                    @if ($olt['health'])
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">{{ $olt['health'] }}</span>
                                    @endif
                                </div>

                                @if (count($olt['ports']) === 0 && empty($olt['loose_onus']['items']))
                                    <p class="mt-4 text-sm text-gray-500">No PON ports or ONUs linked yet.</p>
                                @else
                                    <div class="mt-5 grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                                        @foreach ($olt['ports'] as $port)
                                            <div class="ntree-port rounded-xl border border-cyan-100 bg-cyan-50/40 p-4 dark:border-cyan-900/50 dark:bg-cyan-950/20">
                                                <div class="flex items-center justify-between gap-2">
                                                    <p class="font-semibold text-cyan-900 dark:text-cyan-100">PON {{ $port['label'] }}</p>
                                                    <span class="text-xs text-gray-500">{{ $port['onu_online'] }}/{{ $port['onu_total'] }} up</span>
                                                </div>
                                                <ul class="mt-3 space-y-2">
                                                    @forelse ($port['onus']['items'] as $onu)
                                                        <li class="ntree-onu flex items-start gap-2 rounded-lg bg-white/80 px-2 py-1.5 text-xs dark:bg-gray-900/80">
                                                            <span class="mt-1 h-2 w-2 shrink-0 rounded-full {{ $onu['online'] ? 'bg-emerald-500' : 'bg-rose-400' }}"></span>
                                                            <div class="min-w-0 flex-1">
                                                                <p class="truncate font-mono text-gray-800 dark:text-gray-200">{{ $onu['serial'] ?? $onu['label'] }}</p>
                                                                @if ($onu['customer'])
                                                                    <a href="{{ $onu['customer']['url'] }}" class="truncate text-violet-600 hover:underline">{{ $onu['customer']['name'] }}</a>
                                                                @else
                                                                    <p class="text-gray-400">Unassigned</p>
                                                                @endif
                                                                @if ($onu['rx_dbm'] !== null)
                                                                    <p class="text-gray-400">{{ $onu['rx_dbm'] }} dBm</p>
                                                                @endif
                                                            </div>
                                                        </li>
                                                    @empty
                                                        <li class="text-xs text-gray-400">No ONUs on this port</li>
                                                    @endforelse
                                                    @if ($port['onus']['truncated'])
                                                        <li class="text-xs font-semibold text-amber-700">+ {{ $port['onus']['total'] - count($port['onus']['items']) }} more ONUs</li>
                                                    @endif
                                                </ul>
                                            </div>
                                        @endforeach
                                    </div>

                                    @if (! empty($olt['loose_onus']['items']))
                                        <div class="mt-4 rounded-xl border border-amber-200 bg-amber-50/50 p-4 dark:border-amber-900/40 dark:bg-amber-950/20">
                                            <p class="text-sm font-semibold text-amber-900 dark:text-amber-200">ONUs without PON port</p>
                                            <ul class="mt-2 flex flex-wrap gap-2">
                                                @foreach ($olt['loose_onus']['items'] as $onu)
                                                    <li class="rounded-lg bg-white px-2 py-1 text-xs shadow-sm dark:bg-gray-800">
                                                        <span class="{{ $onu['online'] ? 'text-emerald-600' : 'text-rose-500' }}">●</span>
                                                        {{ $onu['serial'] ?? $onu['label'] }}
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    @endif
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>

        <div x-show="tab === 'geo'" x-cloak>
            <section>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wide text-gray-500">Area → Zone → Subzone</h3>
                @if (count($topology['geo']) === 0)
                    <p class="rounded-xl border border-dashed border-gray-300 p-6 text-sm text-gray-500 dark:border-gray-600">No areas configured.</p>
                @else
                    <div class="space-y-4">
                        @foreach ($topology['geo'] as $area)
                            <details class="ntree-geo-area rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900" open>
                                <summary class="cursor-pointer px-4 py-3 font-semibold text-gray-900 dark:text-white">
                                    {{ $area['name'] }}
                                    <span class="ml-2 rounded-full bg-cyan-100 px-2 py-0.5 text-xs font-bold text-cyan-800">{{ $area['customers'] }} subscribers</span>
                                </summary>
                                <div class="border-t border-gray-100 px-4 py-3 dark:border-gray-800">
                                    @forelse ($area['zones'] as $zone)
                                        <details class="ml-2 mt-2 rounded-lg border border-gray-100 bg-gray-50/80 dark:border-gray-800 dark:bg-gray-950/50">
                                            <summary class="cursor-pointer px-3 py-2 text-sm font-medium text-gray-800 dark:text-gray-200">
                                                {{ $zone['name'] }}
                                                <span class="text-xs text-gray-500">({{ $zone['customers'] }})</span>
                                            </summary>
                                            <ul class="space-y-1 px-4 pb-3 pt-1">
                                                @forelse ($zone['subzones'] as $sub)
                                                    <li class="flex justify-between text-xs text-gray-600 dark:text-gray-400">
                                                        <span>{{ $sub['name'] }}</span>
                                                        <span class="font-semibold">{{ $sub['customers'] }}</span>
                                                    </li>
                                                @empty
                                                    <li class="text-xs text-gray-400">No subzones</li>
                                                @endforelse
                                            </ul>
                                        </details>
                                    @empty
                                        <p class="text-sm text-gray-400">No zones in this area</p>
                                    @endforelse
                                </div>
                            </details>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>

    @push('styles')
        <style>
            .ntree-node {
                display: block;
                min-width: 10rem;
                border-radius: 0.875rem;
                border: 1px solid rgb(221 214 254);
                background: #fff;
                padding: 1rem 1.25rem;
                text-align: center;
                box-shadow: 0 4px 14px rgb(139 92 246 / 0.08);
                transition: transform 0.15s, box-shadow 0.15s;
            }
            .ntree-node:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgb(139 92 246 / 0.15); }
            .ntree-node--core { border-color: rgb(167 139 250); }
            .ntree-dot { display: inline-block; width: 0.5rem; height: 0.5rem; border-radius: 9999px; margin-right: 0.25rem; vertical-align: middle; }
            .ntree-dot--ok { background: rgb(16 185 129); }
            .ntree-dot--warn { background: rgb(251 191 36); }
            [x-cloak] { display: none !important; }
        </style>
    @endpush
</x-filament-panels::page>
