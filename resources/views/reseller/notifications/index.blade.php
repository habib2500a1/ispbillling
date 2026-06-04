@extends('reseller.layout')

@section('title', 'Notifications')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Notifications',
        'subtitle' => 'Payments, commission, wallet, due, and settlements.',
    ])

    @if ($notifications->whereNull('read_at')->count() > 0)
        <div class="mb-4">
            <form method="post" action="{{ route('reseller.notifications.read-all') }}">@csrf<button type="submit" class="rsl-btn-sm rsl-btn-sm--outline">Mark all read</button></form>
        </div>
    @endif

    <div class="space-y-3">
        @forelse ($notifications as $note)
            <div class="rsl-panel rsl-panel-pad {{ $note->read_at ? 'opacity-75' : '' }}" @unless($note->read_at) style="border-left:3px solid var(--rsl-teal-500)" @endunless>
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-semibold">{{ $note->title }}</p>
                        @if ($note->body)
                            <p class="mt-1 text-sm" style="color:var(--rsl-text-muted)">{{ $note->body }}</p>
                        @endif
                        <p class="mt-2 text-xs" style="color:var(--rsl-text-muted)">{{ $note->created_at?->diffForHumans() }} · {{ str_replace('_', ' ', $note->type) }}</p>
                    </div>
                    @if (! $note->read_at)
                        <form method="post" action="{{ route('reseller.notifications.read', $note) }}">@csrf<button type="submit" class="rsl-link-action">Mark read</button></form>
                    @endif
                </div>
            </div>
        @empty
            <div class="rsl-panel rsl-panel-pad text-center" style="color:var(--rsl-text-muted)">No notifications yet.</div>
        @endforelse
    </div>
    @if ($notifications->hasPages())
        <div class="mt-4">{{ $notifications->links() }}</div>
    @endif
@endsection
