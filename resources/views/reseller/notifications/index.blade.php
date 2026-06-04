@extends('reseller.layout')

@section('title', 'Notifications')

@section('content')
    <div class="rsl-card p-6 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="rsl-title">Notifications</h1>
            <p class="rsl-subtitle">Payment, commission, wallet, due reminders and settlement updates</p>
        </div>
        @if ($notifications->whereNull('read_at')->count() > 0)
            <form method="post" action="{{ route('reseller.notifications.read-all') }}">@csrf<button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Mark all read</button></form>
        @endif
    </div>
    <div class="mt-6 space-y-3">
        @forelse ($notifications as $note)
            <div class="rsl-card p-4 {{ $note->read_at ? 'opacity-75' : 'border-l-4 border-l-indigo-500' }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold rsl-text">{{ $note->title }}</p>
                        @if ($note->body)
                            <p class="mt-1 text-sm rsl-text-muted">{{ $note->body }}</p>
                        @endif
                        <p class="mt-2 text-xs rsl-text-muted">{{ $note->created_at?->diffForHumans() }} · {{ str_replace('_', ' ', $note->type) }}</p>
                    </div>
                    @if (! $note->read_at)
                        <form method="post" action="{{ route('reseller.notifications.read', $note) }}">@csrf<button type="submit" class="rsl-link text-xs">Mark read</button></form>
                    @endif
                </div>
            </div>
        @empty
            <div class="rsl-card p-8 text-center rsl-text-muted">No notifications yet.</div>
        @endforelse
    </div>
    <div class="mt-4">{{ $notifications->links() }}</div>
@endsection
