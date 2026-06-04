@extends('reseller.layout')

@section('title', 'Security')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Security & login history</h1>
        <p class="rsl-subtitle mt-1">Recent portal sign-in attempts for your account.</p>
        <div class="mt-4 overflow-x-auto">
            <table class="rsl-table w-full text-sm">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Login</th>
                        <th>Success</th>
                        <th>IP</th>
                        <th>Reason</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($loginLogs as $log)
                        <tr>
                            <td>{{ $log->created_at?->format('d M Y H:i') }}</td>
                            <td>{{ $log->login_id }}</td>
                            <td>{{ $log->success ? 'Yes' : 'No' }}</td>
                            <td>{{ $log->ip_address }}</td>
                            <td>{{ $log->failure_reason ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-4 rsl-text-muted">No login history recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
