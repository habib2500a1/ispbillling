<div>
    <x-slot name="header">
        {{ $title }}
    </x-slot>

    @if(!empty($description))
        <p class="text-muted small mb-3">{{ $description }}</p>
    @endif

    @if(!empty($kpis))
        <div class="row g-3 mb-3">
            @foreach($kpis as $kpi)
                <div class="col-6 col-md-3">
                    <div class="card border-0 shadow-sm h-100 rounded-4">
                        <div class="card-body">
                            <div class="small text-muted text-uppercase fw-semibold">{{ $kpi['label'] }}</div>
                            <div class="fs-4 fw-bold text-{{ $kpi['color'] ?? 'dark' }}">{{ $kpi['value'] }}</div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if(!empty($actions))
        <div class="d-flex flex-wrap gap-2 mb-3">
            @foreach($actions as $action)
                <a href="{{ $action['url'] }}" class="btn btn-sm {{ $action['class'] ?? 'btn-outline-primary' }}" wire:navigate.hover>{{ $action['label'] }}</a>
            @endforeach
            <a href="{{ route('isp-os') }}" class="btn btn-sm btn-outline-dark" wire:navigate.hover>{{ __('ISP OS Center') }}</a>
        </div>
    @endif

    @if(!empty($notice))
        <div class="alert alert-light border rounded-4 mb-3">{{ $notice }}</div>
    @endif

    @if(!empty($columns) && !empty($rows))
        <div class="card border-0 shadow-sm rounded-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            @foreach($columns as $col)
                                <th>{{ $col['label'] }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                @foreach($columns as $col)
                                    <td>{{ $row[$col['key']] ?? '—' }}</td>
                                @endforeach
                            </tr>
                        @empty
                            <tr><td colspan="{{ count($columns) }}" class="text-center text-muted py-4">{{ __('No data yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
