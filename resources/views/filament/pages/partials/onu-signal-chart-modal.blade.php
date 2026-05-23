@php
    /** @var \App\Models\Device $device */
    /** @var array $series */
@endphp
<div class="space-y-3" x-data="{
    init() {
        if (typeof Chart === 'undefined') return;
        const ctx = this.$refs.canvas.getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: @js($series['labels']),
                datasets: [
                    { label: 'RX dBm', data: @js($series['rx']), borderColor: '#10b981', tension: 0.3, spanGaps: true },
                    { label: 'TX dBm', data: @js($series['tx']), borderColor: '#6366f1', tension: 0.3, spanGaps: true },
                ],
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                scales: { y: { title: { display: true, text: 'dBm' } } },
            },
        });
    }
}" x-init="init()">
    <p class="text-sm text-gray-600 dark:text-gray-400">
        <strong>{{ $device->serial_number }}</strong>
        @if($device->customer) · {{ $device->customer->name }} @endif
        · Live optical power history
    </p>
    <canvas x-ref="canvas" height="220"></canvas>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
