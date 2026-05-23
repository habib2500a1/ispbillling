@php
    $health = is_array($device->olt_health) ? $device->olt_health : [];
@endphp

<div class="space-y-4" x-data="{ period: '24h' }">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $device->adminLabel() }}</p>
            <p class="text-sm text-gray-500">{{ $device->management_ip }} · {{ strtoupper((string) ($device->vendor ?? 'OLT')) }}</p>
        </div>
        <div class="flex gap-2 text-xs">
            @foreach (['1h' => '1h', '24h' => '24h', '7d' => '7d', '30d' => '30d'] as $p => $label)
                <span class="rounded px-2 py-1 bg-gray-100 dark:bg-gray-800">{{ $label }}</span>
            @endforeach
        </div>
    </div>

    <div class="grid gap-3 sm:grid-cols-4">
        @foreach ([
            ['CPU', $health['cpu_percent'] ?? null, '%', 'text-cyan-600'],
            ['RAM', $health['memory_percent'] ?? null, '%', 'text-violet-600'],
            ['Temp', $health['temperature_c'] ?? null, '°C', 'text-amber-600'],
            ['Health', $health['health_score'] ?? null, '%', 'text-emerald-600'],
        ] as [$label, $val, $unit, $color])
            <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-700">
                <p class="text-[10px] font-bold uppercase text-gray-500">{{ $label }}</p>
                <p class="text-xl font-bold {{ $color }}">{{ $val !== null ? $val.$unit : '—' }}</p>
            </div>
        @endforeach
    </div>

    <canvas id="olt-health-chart-{{ $device->id }}" height="220"></canvas>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            if (typeof Chart === 'undefined') return;
            const ctx = document.getElementById('olt-health-chart-{{ $device->id }}');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: @json($series['labels']),
                    datasets: [
                        { label: 'CPU %', data: @json($series['cpu']), borderColor: '#22d3ee', tension: 0.3, spanGaps: true },
                        { label: 'RAM %', data: @json($series['memory']), borderColor: '#a78bfa', tension: 0.3, spanGaps: true },
                        { label: 'Health %', data: @json($series['health_score']), borderColor: '#34d399', tension: 0.3, spanGaps: true, yAxisID: 'y1' },
                    ],
                },
                options: {
                    responsive: true,
                    interaction: { mode: 'index', intersect: false },
                    scales: {
                        y: { min: 0, max: 100, title: { display: true, text: 'CPU / RAM %' } },
                        y1: { position: 'right', min: 0, max: 100, grid: { drawOnChartArea: false } },
                    },
                },
            });
        });
    </script>
</div>
