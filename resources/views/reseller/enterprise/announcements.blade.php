@extends('reseller.layout')

@section('title', 'Announcements')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'HQ announcements',
        'subtitle' => 'Published for your account.',
    ])

    <div class="rsl-panel rsl-panel-pad">
        <div class="space-y-4">
            @forelse ($announcements as $item)
                <article class="rsl-settings-tile" style="cursor:default">
                    <p class="text-xs font-semibold uppercase" style="color:var(--rsl-text-muted)">{{ $item->priority }} · {{ $item->published_at?->format('d M Y') }}</p>
                    <h2 class="mt-2">{{ $item->title }}</h2>
                    <div class="mt-2 text-sm whitespace-pre-wrap" style="color:var(--rsl-text-muted)">{{ $item->body }}</div>
                </article>
            @empty
                <p style="color:var(--rsl-text-muted)">No announcements right now.</p>
            @endforelse
        </div>
    </div>
@endsection
