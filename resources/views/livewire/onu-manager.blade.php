<div>
    <x-slot name="header">
        {{ __('Optical / ONU') }}
    </x-slot>

    @if($statusMessage)
        <div class="alert {{ $statusOk ? 'alert-success' : 'alert-warning' }}">{{ $statusMessage }}</div>
    @endif

    @if(! $bridgeEnabled)
        <div class="alert alert-warning">
            {{ __('Same-server ispbilling bridge is off. Set ISPBILLING_OPTICAL_BRIDGE=true and DB credentials, and join Docker network ispbilling_isp.') }}
        </div>
    @else
        <div class="alert alert-info py-2">
            {{ __('Reading live ONU data from ispbilling on this server (same host). Design stays on Code Pagol.') }}
        </div>
    @endif

    <div class="card border-0 shadow-sm rounded-4 mb-3">
        <div class="card-body d-flex flex-wrap gap-2 align-items-center justify-content-between">
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <input type="search" class="form-control" style="max-width: 260px;" wire:model.live.debounce.300ms="search" placeholder="{{ __('Search ONU / customer...') }}">
                <div class="btn-group">
                    <button type="button" class="btn btn-sm {{ $tab === 'local' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTab('local')">{{ __('Linked local') }}</button>
                    <button type="button" class="btn btn-sm {{ $tab === 'remote' ? 'btn-primary' : 'btn-outline-primary' }}" wire:click="setTab('remote')">{{ __('ispbilling live') }}</button>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-sm" wire:click="syncMatched" wire:loading.attr="disabled" wire:confirm="{{ __('Match PPP users to ispbilling ONUs and save locally?') }}">
                <span wire:loading.remove wire:target="syncMatched"><i class="bi bi-arrow-repeat"></i> {{ __('Sync matched from ispbilling') }}</span>
                <span wire:loading wire:target="syncMatched" class="spinner-border spinner-border-sm"></span>
            </button>
        </div>
    </div>

    @if($tab === 'local')
        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Customer') }}</th>
                            <th>PPP</th>
                            <th>RX / TX</th>
                            <th>OLT</th>
                            <th>PON</th>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('Updated') }}</th>
                            <th class="text-end">{{ __('Actions') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($onus as $onu)
                            <tr wire:key="onu-{{ $onu->id }}">
                                <td>
                                    @if($onu->customer)
                                        <a href="{{ route('customers.edit', encrypt($onu->customer->customer_unique_id)) }}" class="fw-semibold text-decoration-none">
                                            {{ $onu->customer->customer_name }}
                                        </a>
                                        <div class="small text-muted">{{ $onu->customer->customer_unique_id }}</div>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="font-monospace small">{{ $onu->customer?->pppUser?->username ?: '—' }}</td>
                                <td class="font-monospace">
                                    {{ $onu->rx_power_dbm !== null ? number_format((float)$onu->rx_power_dbm, 2).' dBm' : '—' }}
                                    <span class="text-muted">/</span>
                                    {{ $onu->tx_power_dbm !== null ? number_format((float)$onu->tx_power_dbm, 2).' dBm' : '—' }}
                                </td>
                                <td>{{ $onu->olt_name ?: '—' }}</td>
                                <td class="font-monospace small">{{ $onu->pon_port ?: '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $onu->oper_status ?: $onu->source }}</span></td>
                                <td class="small">{{ optional($onu->last_polled_at)->diffForHumans() ?? '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <button type="button" class="btn btn-sm btn-outline-success" wire:click="refreshCustomer({{ $onu->id }})">{{ __('Refresh') }}</button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" wire:click="deleteLocal({{ $onu->id }})" wire:confirm="{{ __('Delete local ONU row?') }}">{{ __('Delete') }}</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    {{ __('No linked ONUs yet. Open ispbilling live tab or Sync matched.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($onus->hasPages())
                <div class="card-footer">{{ $onus->links() }}</div>
            @endif
        </div>
    @else
        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('ispbilling customer') }}</th>
                            <th>PPP</th>
                            <th>RX / TX</th>
                            <th>OLT</th>
                            <th>PON</th>
                            <th>MAC</th>
                            <th>{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($remoteOnus as $row)
                            <tr wire:key="remote-{{ $row->onu_id }}">
                                <td class="fw-semibold">{{ $row->customer_name ?: '—' }}</td>
                                <td class="font-monospace small">{{ $row->radius_username ?: '—' }}</td>
                                <td class="font-monospace">
                                    {{ $row->rx_power_dbm !== null ? number_format((float)$row->rx_power_dbm, 2).' dBm' : '—' }}
                                    /
                                    {{ $row->tx_power_dbm !== null ? number_format((float)$row->tx_power_dbm, 2).' dBm' : '—' }}
                                </td>
                                <td>{{ $row->olt_name ?: '—' }}</td>
                                <td class="font-monospace small">{{ $row->display_name ?: '—' }}</td>
                                <td class="font-monospace small">{{ $row->mac_address ?: '—' }}</td>
                                <td><span class="badge bg-secondary">{{ $row->onu_oper_status ?: '—' }}</span></td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">{{ __('No remote ONU rows (bridge off or empty).') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
