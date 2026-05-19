@extends('portal.layout')

@section('title', 'Notifications')

@section('content')
    <h1 class="text-2xl font-bold text-slate-900">Notifications</h1>
    <p class="mt-1 text-sm text-slate-600">Bills, outages, optical alerts, and payments.</p>

    <ul class="mt-8 space-y-3">
        @forelse ($items as $item)
            @php
                $bg = match ($item['severity']) {
                    'danger' => 'border-rose-200 bg-rose-50',
                    'warning' => 'border-amber-200 bg-amber-50',
                    'success' => 'border-emerald-200 bg-emerald-50',
                    default => 'border-slate-200 bg-white',
                };
            @endphp
            <li class="rounded-xl border px-4 py-3 {{ $bg }}">
                <p class="font-semibold text-slate-900">{{ $item['title'] }}</p>
                <p class="mt-1 text-sm text-slate-700">{{ $item['message'] }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ \Carbon\Carbon::parse($item['at'])->diffForHumans() }}</p>
            </li>
        @empty
            <li class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-10 text-center text-slate-500">No alerts right now.</li>
        @endforelse
    </ul>
@endsection
