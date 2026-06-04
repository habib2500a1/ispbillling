@extends('reseller.layout')

@section('title', 'Internal support')

@section('content')
    @include('reseller.partials.page-header', [
        'title' => 'Internal support',
        'subtitle' => 'HQ / platform issues — not customer tickets.',
    ])

    <div class="rsl-panel rsl-panel-pad" style="max-width:36rem">
        <h2 class="rsl-panel-title">New ticket</h2>
        <form method="post" action="{{ route('reseller.internal-tickets.store') }}" class="rsl-form-grid mt-4">
            @csrf
            <div class="rsl-field">
                <label class="rsl-field-label" for="subject">Subject</label>
                <input id="subject" type="text" name="subject" required class="rsl-input">
            </div>
            <div class="rsl-field">
                <label class="rsl-field-label" for="body">Details</label>
                <textarea id="body" name="body" required rows="4" class="rsl-input"></textarea>
            </div>
            <button type="submit" class="rsl-btn">Submit</button>
        </form>
    </div>

    <div class="rsl-panel rsl-panel-pad mt-6">
        <h2 class="rsl-panel-title">History</h2>
        <ul class="mt-4 space-y-3">
            @forelse ($tickets as $ticket)
                <li class="rsl-settings-tile" style="cursor:default">
                    <span class="font-semibold">{{ $ticket->subject }}</span>
                    <span class="ml-2 text-xs" style="color:var(--rsl-text-muted)">{{ $ticket->status }} · {{ $ticket->created_at->diffForHumans() }}</span>
                    <p class="mt-2 text-sm" style="color:var(--rsl-text-muted)">{{ \Illuminate\Support\Str::limit($ticket->body, 200) }}</p>
                </li>
            @empty
                <p style="color:var(--rsl-text-muted)">No tickets yet.</p>
            @endforelse
        </ul>
    </div>
@endsection
