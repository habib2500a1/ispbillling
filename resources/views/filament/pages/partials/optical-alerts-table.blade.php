@php
    $alerts = $this->getOpenAlertsPayload();
@endphp

<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                <th class="px-3 py-2">Severity</th>
                <th class="px-3 py-2">Title</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2">ONU</th>
                <th class="px-3 py-2">RX dBm</th>
                <th class="px-3 py-2">Detected</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($alerts as $alert)
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="px-3 py-2">{{ $alert->severity }}</td>
                    <td class="px-3 py-2">{{ $alert->title }}</td>
                    <td class="px-3 py-2">{{ $alert->alert_type }}</td>
                    <td class="px-3 py-2 font-mono text-xs">{{ $alert->device?->serial_number ?? '—' }}</td>
                    <td class="px-3 py-2 tabular-nums">
                        {{ $alert->rx_power_dbm !== null ? number_format((float) $alert->rx_power_dbm, 2).' dBm' : '—' }}
                    </td>
                    <td class="px-3 py-2 text-xs">{{ $alert->detected_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-3 py-8 text-center text-gray-500">No open optical alerts.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
