@php
    use App\Filament\Resources\CustomerResource;
    $alerts = $this->getOpenAlertsPayload();
@endphp

<div class="overflow-x-auto rounded-lg border border-gray-200 dark:border-gray-700">
    <table class="w-full text-left text-sm">
        <thead>
            <tr class="border-b bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                <th class="px-3 py-2">Severity</th>
                <th class="px-3 py-2">Title</th>
                <th class="px-3 py-2">Type</th>
                <th class="px-3 py-2">PPP username</th>
                <th class="px-3 py-2">Client</th>
                <th class="px-3 py-2">ONU / MAC</th>
                <th class="px-3 py-2">RX dBm</th>
                <th class="px-3 py-2">Detected</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($alerts as $alert)
                @php
                    $customer = $alert->customer ?? $alert->device?->customer;
                    $deviceMeta = is_array($alert->device?->meta) ? $alert->device->meta : [];
                    $username = $customer?->pppLoginName()
                        ?: ($deviceMeta['ppp_login'] ?? $deviceMeta['subscriber_login'] ?? $deviceMeta['bdcom_description'] ?? null);
                    $onuLabel = $alert->device?->mac_address
                        ?: $alert->device?->serial_number
                        ?: ($alert->olt?->display_name ?? '—');
                @endphp
                <tr class="border-b border-gray-100 dark:border-gray-800">
                    <td class="px-3 py-2">{{ $alert->severity }}</td>
                    <td class="px-3 py-2">{{ $alert->title }}</td>
                    <td class="px-3 py-2">{{ $alert->alert_type }}</td>
                    <td class="px-3 py-2 font-mono text-xs">
                        @if (filled($username))
                            @if ($customer)
                                <a href="{{ CustomerResource::getUrl('view', ['record' => $customer]) }}" class="text-primary-600 hover:underline dark:text-primary-400">
                                    {{ $username }}
                                </a>
                            @else
                                {{ $username }}
                            @endif
                        @else
                            <span class="text-gray-400">—</span>
                        @endif
                    </td>
                    <td class="px-3 py-2 text-xs">
                        @if ($customer)
                            <span>{{ $customer->name }}</span>
                            @if (filled($customer->customer_code))
                                <span class="block font-mono text-gray-500">{{ $customer->customer_code }}</span>
                            @endif
                        @elseif ($alert->olt)
                            <span class="text-gray-500">{{ $alert->olt->display_name ?? $alert->olt->serial_number }} (PON/OLT)</span>
                        @else
                            —
                        @endif
                    </td>
                    <td class="px-3 py-2 font-mono text-xs text-gray-500">{{ $onuLabel }}</td>
                    <td class="px-3 py-2 tabular-nums">
                        {{ $alert->rx_power_dbm !== null ? number_format((float) $alert->rx_power_dbm, 2).' dBm' : '—' }}
                    </td>
                    <td class="px-3 py-2 text-xs">{{ $alert->detected_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-3 py-8 text-center text-gray-500">No open optical alerts.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
