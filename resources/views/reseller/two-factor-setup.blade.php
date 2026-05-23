@extends('reseller.layout')

@section('title', 'Setup 2FA')

@section('content')
    <div class="rsl-card p-6 max-w-md mx-auto text-center">
        <h1 class="text-xl font-bold">Enable two-factor</h1>
        <img src="{{ $qrUrl }}" alt="QR" class="mx-auto mt-4 rounded-lg border">
        <p class="mt-2 text-xs font-mono break-all">{{ $secret }}</p>
        <form method="post" action="{{ route('reseller.two-factor.confirm') }}" class="mt-4">
            @csrf
            <input name="code" required placeholder="6-digit code" class="w-full rounded-lg border px-3 py-2 text-center">
            <button type="submit" class="rsl-btn w-full mt-3">Confirm</button>
        </form>
    </div>
@endsection
