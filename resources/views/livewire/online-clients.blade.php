<div class="zoom-in">
    <x-slot name="header">{{ __('Online Clients') }}</x-slot>

    <div class="card border-0 shadow-sm rounded-4">
        <div class="card-body">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <input type="search" class="form-control form-control-sm" style="max-width:220px"
                    wire:model.live.debounce.300ms="search" placeholder="{{ __('Search user / profile') }}">

                <select class="form-select form-select-sm" style="max-width:140px" wire:model.live="filter">
                    <option value="online">{{ __('Online') }}</option>
                    <option value="offline">{{ __('Offline') }}</option>
                    <option value="all">{{ __('All') }}</option>
                </select>

                <select class="form-select form-select-sm" style="max-width:160px" wire:model.live="routerFilter">
                    <option value="">{{ __('All routers') }}</option>
                    @foreach ($routers as $rn)
                        <option value="{{ $rn }}">{{ $rn }}</option>
                    @endforeach
                </select>

                <button type="button" class="btn btn-sm btn-primary" wire:click="refreshOnline" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="refreshOnline"><i class="bi bi-arrow-repeat me-1"></i>{{ __('Refresh') }}</span>
                    <span wire:loading wire:target="refreshOnline" class="spinner-border spinner-border-sm"></span>
                </button>

                <span class="badge bg-success">{{ __('Online') }}: {{ $onlineCount }}</span>
                <span class="badge bg-secondary">{{ __('Offline') }}: {{ $offlineCount }}</span>
            </div>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>{{ __('Status') }}</th>
                            <th>{{ __('PPP User') }}</th>
                            <th>{{ __('Router') }}</th>
                            <th>{{ __('Profile') }}</th>
                            <th>{{ __('Customer') }}</th>
                            <th>{{ __('Updated') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rows as $row)
                            @php $isOnline = !empty($row->uptime); @endphp
                            <tr>
                                <td>
                                    @if($isOnline)
                                        <span class="badge bg-success"><i class="bi bi-broadcast me-1"></i>{{ __('Online') }}</span>
                                    @else
                                        <span class="badge bg-secondary">{{ __('Offline') }}</span>
                                    @endif
                                </td>
                                <td class="fw-semibold">{{ $row->username }}</td>
                                <td>{{ $row->router_name }}</td>
                                <td>{{ $row->profile }}</td>
                                <td>
                                    @if($row->customer)
                                        {{ $row->customer->customer_name }}
                                        <div class="small text-muted">{{ $row->customer->customer_unique_id }}</div>
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="small text-muted">{{ $row->uptime ? \Carbon\Carbon::parse($row->uptime)->diffForHumans() : '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-muted text-center py-4">{{ __('No PPP users found.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">{{ $rows->links() }}</div>
        </div>
    </div>
</div>
