@extends('reseller.layout')

@section('title', 'Internal support')

@section('content')
    <div class="rsl-card p-6">
        <h1 class="rsl-title">Internal support tickets</h1>
        <form method="post" action="{{ route('reseller.internal-tickets.store') }}" class="mt-4 max-w-xl space-y-3">
            @csrf
            <input type="text" name="subject" required placeholder="Subject" class="rsl-input w-full">
            <textarea name="body" required rows="4" placeholder="Describe your issue" class="rsl-input w-full"></textarea>
            <button type="submit" class="rsl-btn-sm">Submit ticket</button>
        </form>
        <ul class="mt-8 space-y-3 text-sm">
            @foreach ($tickets as $ticket)
                <li class="border rounded-lg p-3">
                    <span class="font-semibold">{{ $ticket->subject }}</span>
                    <span class="ml-2 text-xs rsl-text-muted">{{ $ticket->status }} · {{ $ticket->created_at->diffForHumans() }}</span>
                    <p class="mt-2 rsl-text-muted">{{ \Illuminate\Support\Str::limit($ticket->body, 200) }}</p>
                </li>
            @endforeach
        </ul>
    </div>
@endsection
