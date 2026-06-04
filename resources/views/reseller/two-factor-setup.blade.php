@extends('reseller.layout')

@section('title', 'Setup 2FA')

@section('content')
    <div class="rsl-2fa-narrow">
        <div class="rsl-panel rsl-panel-pad text-center">
            <h1 class="rsl-page-title">Enable two-factor</h1>
            <p class="rsl-page-sub mt-2">Scan the QR code with Google Authenticator</p>
            <img src="{{ $qrUrl }}" alt="QR" class="mx-auto mt-4 rounded-lg border" style="margin:1rem auto;border-radius:var(--rsl-radius);border:1px solid var(--rsl-border)">
            <p class="mt-2 text-xs font-mono break-all" style="color:var(--rsl-text-muted)">{{ $secret }}</p>
            <form method="post" action="{{ route('reseller.two-factor.confirm') }}" class="mt-4">
                @csrf
                <input name="code" required placeholder="6-digit code" class="rsl-input text-center">
                <button type="submit" class="rsl-btn w-full mt-3" style="width:100%;margin-top:0.75rem">Confirm</button>
            </form>
        </div>
    </div>
@endsection
