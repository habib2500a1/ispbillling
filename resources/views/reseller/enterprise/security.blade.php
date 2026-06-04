@extends('reseller.layout')

@section('title', 'Security')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Security & login',
        'subtitle' => 'Your portal sign-in history.',
    ])

    <div class="rsl-panel">
        <div class="rsl-table-wrap">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">Login</th>
                        <th class="px-4 py-3">Success</th>
                        <th class="px-4 py-3">IP</th>
                        <th class="px-4 py-3">Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loginLogs as $log)
                        <tr>
                            <td class="px-4 py-3">{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $log->login_id }}</td>
                            <td class="px-4 py-3">
                                <span class="rsl-badge-pill {{ $log->success ? 'rsl-badge-pill--ok' : 'rsl-badge-pill--muted' }}">
                                    {{ $log->success ? 'Yes' : 'No' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">{{ $log->ip_address }}</td>
                            <td class="px-4 py-3">{{ $log->failure_reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center" style="color:var(--rsl-text-muted)">No login history yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
