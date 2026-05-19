@extends('portal.layout')

@section('title', 'Live chat')

@section('content')
    <h1 class="mb-4 text-2xl font-semibold text-slate-900">Live chat</h1>
    <p class="mb-6 text-sm text-slate-600">Chat is handled through a support ticket so our team can reply when they are online.</p>

    @if ($openTicket)
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-900">
            You have an open chat session:
            <a href="{{ route('portal.tickets.show', $openTicket) }}" class="font-semibold underline">{{ $openTicket->ticket_number }}</a>
        </div>
    @else
        <form method="post" action="{{ route('portal.live-chat.start') }}" class="max-w-md">
            @csrf
            <button type="submit" class="rounded-lg bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                Start chat session
            </button>
        </form>
    @endif
@endsection
