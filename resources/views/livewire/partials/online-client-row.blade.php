@php
    $isOnline = !empty($row->uptime);
    $c = $row->customer;
    $onu = $c?->onus?->sortByDesc('last_polled_at')->first();
    $reason = $isOnline ? '' : \App\Livewire\OnlineClients::disconnectLabel($row->last_disconnect_reason);
@endphp
<tr>
    <td>
        @if($isOnline)
            <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i>{{ __('Connected') }}</span>
        @else
            <span class="badge bg-secondary">{{ __('Offline') }}</span>
        @endif
        <div class="small oc-muted">{{ $row->router_name }}</div>
    </td>
    <td>
        <div class="fw-semibold">{{ $c->customer_name ?? $row->username }}</div>
        <div class="oc-mono small">{{ $row->username }}</div>
        @if($c)
            <div class="small oc-muted">{{ $c->customer_unique_id }} · {{ $c->mobile ?: '—' }}</div>
            @if($c->address)<div class="small oc-muted">{{ $c->address }}</div>@endif
        @endif
    </td>
    <td class="small">
        <div>{{ $c?->official?->connection_type ?: ($row->service ?: 'PPPoE') }}</div>
        <div>{{ $c?->package?->package ?? $row->profile }}</div>
        @if($isOnline && $row->ppp_remote_ip)
            <button type="button" class="btn btn-link p-0 oc-link" wire:click="openTraffic({{ $row->id }})">{{ $row->ppp_remote_ip }}</button>
        @else
            <div class="oc-mono">{{ $row->ppp_remote_ip ?: '—' }}</div>
        @endif
        <div class="oc-muted oc-mono">{{ $row->caller_id ?: $row->last_caller_id ?: '—' }}</div>
    </td>
    <td class="small">
        @if($onu)
            <div class="fw-semibold">RX {{ number_format((float) $onu->rx_power_dbm, 1) }} dBm</div>
            <div>TX {{ number_format((float) $onu->tx_power_dbm, 1) }} dBm</div>
            <div class="oc-muted">{{ $onu->pon_port ?: '—' }}</div>
        @else
            <span class="oc-muted">—</span>
        @endif
    </td>
    <td class="small">
        @include('livewire.partials.traffic-usage-pills', ['usage' => $usages[$row->id] ?? [], 'compact' => true])
    </td>
    <td class="small">
        @if($isOnline)
            <div class="text-success fw-semibold">{{ \App\Livewire\OnlineClients::sessionDuration($row->uptime) }}</div>
            <div>{{ \Carbon\Carbon::parse($row->uptime)->format('d/m/Y h:i:s A') }}</div>
        @else
            <span class="oc-muted">—</span>
        @endif
    </td>
    <td class="small">
        @if(! $isOnline)
            <div>{{ $row->last_logged_out ? \Carbon\Carbon::parse($row->last_logged_out)->format('d/m/Y h:i A') : '—' }}</div>
            @if($reason)<div class="text-warning">{{ $reason }}</div>@endif
        @else
            <span class="oc-muted">—</span>
        @endif
    </td>
    <td>
        <div class="oc-actions">
            <button type="button" class="btn btn-sm oc-icon" @click="onu = {{ \Illuminate\Support\Js::from([
                'id' => $row->id,
                'user' => $row->username,
                'canSync' => (bool) $c,
                'rx' => $onu?->rx_power_dbm !== null ? number_format((float) $onu->rx_power_dbm, 1) : '',
                'tx' => $onu?->tx_power_dbm !== null ? number_format((float) $onu->tx_power_dbm, 1) : '',
                'pon' => $onu?->pon_port ?: '',
                'olt' => $onu?->olt_name ?: '',
                'mac' => $onu?->mac_address ?: '',
                'status' => $onu?->oper_status ?: '',
                'polled' => $onu?->last_polled_at ? $onu->last_polled_at->diffForHumans() : '',
            ]) }}" title="{{ __('ONU details') }}"><i class="bi bi-diagram-3"></i></button>
            <button type="button" class="btn btn-sm oc-icon" wire:click="refreshOne({{ $row->id }})" wire:loading.attr="disabled" title="{{ __('Refresh') }}"><i class="bi bi-arrow-repeat"></i></button>
            <button type="button" class="btn btn-sm oc-icon" @disabled(! $isOnline) wire:click="openTraffic({{ $row->id }})" title="{{ __('Live traffic') }}"><i class="bi bi-bar-chart-line"></i></button>
            @if($c)
                <a class="btn btn-sm oc-icon" href="{{ route('customers.show', encrypt($c->customer_unique_id)) }}" title="{{ __('Profile') }}"><i class="bi bi-person-badge"></i></a>
            @endif
        </div>
    </td>
</tr>
