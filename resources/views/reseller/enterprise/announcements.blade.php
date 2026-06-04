@extends('reseller.layout')

@section('title', 'Announcements')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Announcements</h1>
        <div class="mt-4 space-y-4">
            @forelse ($announcements as $item)
                <article class="rounded-lg border border-slate-200 p-4">
                    <p class="text-xs font-semibold uppercase rsl-text-muted">{{ $item->priority }} · {{ $item->published_at?->format('d M Y') }}</p>
                    <h2 class="mt-1 font-semibold rsl-text">{{ $item->title }}</h2>
                    <div class="mt-2 text-sm rsl-text-muted whitespace-pre-wrap">{{ $item->body }}</div>
                </article>
            @empty
                <p class="rsl-text-muted">No announcements at this time.</p>
            @endforelse
        </div>
    </div>
@endsection
