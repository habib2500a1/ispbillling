@php
    $payload = $this->getAlarmPayload();
    $summary = $payload['summary'] ?? [];
    $alarms = $payload['alarms'] ?? [];
@endphp

{!! \App\Support\OltStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-olt-alarm-page" wire:poll.60s>
    <div class="olt-pro space-y-5">
        <header class="olt-hero">
            <div class="olt-hero__grid">
                <span class="olt-hero__badge">
                    <span class="olt-hero__badge-dot" aria-hidden="true"></span>
                    Unified alarm center
                </span>
                <h1 class="olt-hero__title">OLT &amp; optical alarms</h1>
                <p class="olt-hero__sub">
                    Signal alerts, fiber faults, high CPU/temperature, and chassis health — aggregated across all OLTs.
                </p>
            </div>
        </header>

        <div class="olt-oc-grid">
            @foreach ([
                ['label' => 'Total open', 'value' => $summary['total'] ?? 0, 'tone' => 'indigo'],
                ['label' => 'Critical', 'value' => $summary['critical'] ?? 0, 'tone' => 'rose'],
                ['label' => 'Warning', 'value' => $summary['warning'] ?? 0, 'tone' => 'amber'],
                ['label' => 'Fiber cuts', 'value' => $summary['fiber_cut'] ?? 0, 'tone' => 'rose'],
                ['label' => 'PON down', 'value' => $summary['pon_down'] ?? 0, 'tone' => 'violet'],
                ['label' => 'High temp', 'value' => $summary['temperature'] ?? 0, 'tone' => 'amber'],
            ] as $kpi)
                <article @class(['olt-oc-kpi', 'olt-oc-kpi--'.$kpi['tone']])>
                    <span class="olt-oc-kpi__label">{{ $kpi['label'] }}</span>
                    <strong class="olt-oc-kpi__value">{{ number_format($kpi['value']) }}</strong>
                </article>
            @endforeach
        </div>

        <section class="olt-oc-panel">
            <div class="olt-oc-panel__head">Active alarms (auto-refresh 60s)</div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="border-b bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                            <th class="px-3 py-2">Severity</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">OLT</th>
                            <th class="px-3 py-2">Description</th>
                            <th class="px-3 py-2">Detected</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($alarms as $alarm)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="px-3 py-2">
                                    <span @class([
                                        'rounded px-2 py-0.5 text-xs font-semibold uppercase',
                                        'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-200' => ($alarm['severity'] ?? '') === 'critical',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-200' => ($alarm['severity'] ?? '') === 'warning',
                                        'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' => ! in_array($alarm['severity'] ?? '', ['critical', 'warning'], true),
                                    ])>{{ $alarm['severity'] ?? 'info' }}</span>
                                </td>
                                <td class="px-3 py-2 font-mono text-xs">{{ str_replace('_', ' ', $alarm['type'] ?? '—') }}</td>
                                <td class="px-3 py-2 font-medium">{{ $alarm['title'] ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $alarm['olt'] ?? '—' }}</td>
                                <td class="px-3 py-2 text-xs text-gray-600 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($alarm['description'] ?? '—', 120) }}</td>
                                <td class="px-3 py-2 text-xs whitespace-nowrap">
                                    @if (! empty($alarm['detected_at']))
                                        {{ \Illuminate\Support\Carbon::parse($alarm['detected_at'])->diffForHumans() }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-3 py-10 text-center text-gray-500">No active alarms — fleet healthy.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
