@extends('reseller.layout')

@section('title', 'Activity log')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Activity log',
        'subtitle' => 'Portal and API activity.',
    ])

    <div class="rsl-panel rsl-panel-pad">
        <form method="get" class="rsl-toolbar">
            <div class="rsl-field" style="margin:0">
                <label class="rsl-field-label">Action</label>
                <select name="action" class="rsl-input" style="min-width:12rem">
                    <option value="">All actions</option>
                    @foreach ($actionOptions as $key => $label)
                        <option value="{{ $key }}" @selected(request('action') === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="rsl-btn-sm">Filter</button>
        </form>
    </div>
    <div class="rsl-panel mt-4 overflow-hidden">
        <table class="rsl-table w-full text-sm">
            <thead>
                <tr>
                    <th class="px-4 py-3">When</th>
                    <th class="px-4 py-3">Action</th>
                    <th class="px-4 py-3">By</th>
                    <th class="px-4 py-3">Details</th>
                    <th class="px-4 py-3">IP</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr>
                        <td class="px-4 py-3 rsl-text whitespace-nowrap">{{ $log->created_at?->format('d M Y H:i') }}</td>
                        <td class="px-4 py-3 rsl-text">{{ $log->actionLabel() }}</td>
                        <td class="px-4 py-3 rsl-text-muted">{{ $log->actorLabel() }}</td>
                        <td class="px-4 py-3 rsl-text-muted text-xs">
                            @if ($log->subjectLabel())
                                {{ $log->subjectLabel() }}
                            @endif
                            @if (is_array($log->meta) && $log->meta !== [])
                                @if ($log->subjectLabel()) · @endif
                                @foreach ($log->meta as $k => $v)
                                    {{ $k }}: {{ is_scalar($v) ? $v : json_encode($v) }}@if (! $loop->last) · @endif
                                @endforeach
                            @endif
                            @if (! $log->subjectLabel() && empty($log->meta))
                                —
                            @endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs rsl-text-muted">{{ $log->ip_address ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center rsl-text-muted">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        <div class="p-4">{{ $logs->links() }}</div>
    </div>
@endsection
