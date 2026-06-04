@php
    $payload = $this->getOpticalDatabasePayload();
    $summary = $payload['summary'];
    $paginator = $payload['rows'];
    $rows = $paginator->items();
@endphp

<div class="space-y-3 isp-optical-db-panel">
    <div class="isp-optical-db-toolbar rounded-xl border border-slate-200 bg-white px-4 py-3 dark:border-slate-700 dark:bg-slate-900">
        <div class="isp-optical-db-toolbar__stats flex flex-wrap gap-4 text-sm">
            <span><strong>{{ number_format($summary['total']) }}</strong> ONU</span>
            <span class="text-emerald-600"><strong>{{ number_format($summary['with_rx']) }}</strong> with OpticalPower</span>
            <span class="text-violet-600"><strong>{{ number_format($summary['linked']) }}</strong> linked subscriber</span>
            @if (! empty($summary['auto_detected']))
                <span class="text-cyan-600"><strong>{{ number_format($summary['auto_detected']) }}</strong> auto-detected from OLT</span>
            @endif
        </div>
        <div class="isp-optical-db-toolbar__controls flex flex-wrap items-center gap-3">
            <label class="isp-optical-db-toolbar__control flex items-center gap-2 text-xs font-semibold uppercase text-gray-500">
                Show
                <select wire:model.live="opticalDbPerPage" class="rounded border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800">
                    @foreach ([10, 25, 50, 100, 200] as $n)
                        <option value="{{ $n }}">{{ $n }}</option>
                    @endforeach
                </select>
                entries
            </label>
            <label class="isp-optical-db-toolbar__control flex items-center gap-2 text-xs font-semibold uppercase text-gray-500">
                Search
                <input type="search" wire:model.live.debounce.400ms="opticalDbSearch"
                    placeholder="OLT, ONU07/05, C1/P7, MAC, client…"
                    class="w-48 rounded border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800 sm:w-64" />
            </label>
        </div>
    </div>

    <div class="isp-optical-power-wrap overflow-x-auto rounded-lg border border-gray-200 shadow-sm dark:border-gray-700">
        <table class="isp-optical-power-table min-w-full text-left text-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Client Code</th>
                    <th>PPP username</th>
                    <th>Client Name</th>
                    <th>MacAddress</th>
                    <th>IpAddress</th>
                    <th>OLTName</th>
                    <th>PON port</th>
                    <th>MikroTik</th>
                    <th>VLAN</th>
                    <th class="isp-optical-power-col">Received power (RX dBm)</th>
                    <th>TX (dBm)</th>
                    <th>Temp</th>
                    <th>Voltage</th>
                    <th>OnuMacaddress</th>
                    <th>PON port</th>
                    <th>OnuStatus</th>
                    <th>Description</th>
                    <th>LastDeregisterTime</th>
                    <th>Distance</th>
                    <th>DeregisterReason</th>
                    <th>Last Synced Time</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr @class(['isp-optical-power-row--high' => ! empty($row['is_high_laser'])])>
                        <td data-label="#">{{ $row['index'] }}</td>
                        <td data-label="Client Code" class="font-mono text-xs">
                            @if (! empty($row['customer_id']))
                                <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('view', ['record' => $row['customer_id']]) }}" class="text-cyan-600 hover:underline">
                                    {{ $row['client_code'] }}
                                </a>
                            @else
                                {{ $row['client_code'] }}
                            @endif
                        </td>
                        <td data-label="PPP username" class="font-mono text-xs">{{ $row['username'] }}</td>
                        <td data-label="Client Name">{{ $row['client_name'] }}</td>
                        <td data-label="MacAddress" class="font-mono text-xs whitespace-nowrap">{{ $row['mac_address'] }}</td>
                        <td data-label="IpAddress" class="font-mono text-xs whitespace-nowrap">{{ $row['ip_address'] }}</td>
                        <td data-label="OLTName">{{ $row['olt_name'] }}</td>
                        <td data-label="PON port" class="font-mono text-xs whitespace-nowrap">{{ $row['olt_port'] }}</td>
                        <td data-label="MikroTik" class="text-xs font-semibold text-cyan-700 dark:text-cyan-300">{{ $row['mikrotik_name'] ?? '—' }}</td>
                        <td data-label="VLAN" class="font-mono text-xs">
                            @if (($row['vlan'] ?? '—') !== '—')
                                <span class="rounded bg-emerald-500/15 px-1 font-bold text-emerald-800 dark:text-emerald-200">{{ $row['vlan'] }}</span>
                            @else
                                —
                            @endif
                        </td>
                        <td data-label="Received power (RX dBm)" class="isp-optical-power-col">
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
                        <td data-label="TX (dBm)" class="font-mono text-xs tabular-nums">{{ $row['tx_power'] ?? '—' }}</td>
                        <td data-label="Temp" class="font-mono text-xs tabular-nums">{{ $row['temperature'] ?? '—' }}</td>
                        <td data-label="Voltage" class="font-mono text-xs tabular-nums">{{ $row['voltage'] ?? '—' }}</td>
                        <td data-label="OnuMacaddress" class="font-mono text-xs whitespace-nowrap">{{ $row['onu_mac'] }}</td>
                        <td data-label="PON port" class="font-mono text-xs whitespace-nowrap">{{ $row['olt_port'] }}</td>
                        <td data-label="OnuStatus">
                            <span @class([
                                'isp-optical-status',
                                'isp-optical-status--online' => strtolower($row['onu_status']) === 'online',
                                'isp-optical-status--offline' => strtolower($row['onu_status']) !== 'online',
                            ])>{{ $row['onu_status'] }}</span>
                        </td>
                        <td data-label="Description" class="max-w-[10rem] truncate" title="{{ $row['description'] }}">{{ $row['description'] }}</td>
                        <td data-label="LastDeregisterTime" class="text-xs whitespace-nowrap">{{ $row['last_deregister_time'] }}</td>
                        <td data-label="Distance" class="tabular-nums">{{ $row['distance'] }}</td>
                        <td data-label="DeregisterReason" class="text-xs">{{ $row['deregister_reason'] }}</td>
                        <td data-label="Last Synced Time" class="text-xs whitespace-nowrap">{{ $row['last_synced_time'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="22" class="py-10 text-center text-gray-500">
                            No ONU data — add OLT and run <strong>Poll OLT health</strong> or BDCOM/Huawei sync.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="isp-optical-db-pagination flex flex-wrap items-center justify-between gap-2 text-sm text-gray-600 dark:text-gray-400">
        <p class="isp-optical-db-pagination__meta">
            Showing {{ $paginator->firstItem() ?? 0 }}–{{ $paginator->lastItem() ?? 0 }} of {{ number_format($paginator->total()) }}
            · Received power = OLT ONU RX (received power DBm) · refresh after BDCOM sync
        </p>
        <div class="flex items-center gap-2">
            <button type="button" wire:click="gotoOpticalDbPage({{ max(1, $paginator->currentPage() - 1) }})"
                @disabled($paginator->onFirstPage())
                class="rounded border px-3 py-1 text-xs font-semibold disabled:opacity-40 dark:border-gray-600">Prev</button>
            <span class="text-xs">Page {{ $paginator->currentPage() }} / {{ $paginator->lastPage() }}</span>
            <button type="button" wire:click="gotoOpticalDbPage({{ min($paginator->lastPage(), $paginator->currentPage() + 1) }})"
                @disabled(! $paginator->hasMorePages())
                class="rounded border px-3 py-1 text-xs font-semibold disabled:opacity-40 dark:border-gray-600">Next</button>
        </div>
    </div>
</div>
