<div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
        <thead class="table-light">
            <tr>
                <th>{{ __('When') }}</th>
                <th>{{ __('Customer') }}</th>
                <th>{{ __('Outcome') }}</th>
                <th>{{ __('Staff') }}</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            @forelse($rows as $row)
                <tr>
                    <td>
                        <div>{{ $row['called_at'] }}</div>
                        <div class="small text-muted">{{ $row['called_human'] }}</div>
                    </td>
                    <td>
                        <div class="fw-semibold text-truncate" style="max-width:140px;">{{ $row['customer_name'] }}</div>
                        <div class="small text-muted">{{ $row['phone'] ?: '—' }}</div>
                    </td>
                    <td>
                        <span class="badge bg-{{ $row['outcome'] === 'callback' ? 'info' : ($row['outcome'] === 'answered' ? 'success' : 'warning') }}">
                            {{ $row['outcome_label'] }}
                        </span>
                        <div class="small text-muted">{{ $row['direction'] }} · {{ $row['duration_seconds'] }}s</div>
                    </td>
                    <td class="small">{{ $row['staff'] ?: '—' }}</td>
                    <td>
                        @if($row['customer_unique_id'])
                            <button type="button" class="btn btn-sm btn-outline-primary"
                                wire:click="selectFromQueue('{{ $row['customer_unique_id'] }}')">{{ __('Open') }}</button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted small">{{ __('No call logs yet.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
