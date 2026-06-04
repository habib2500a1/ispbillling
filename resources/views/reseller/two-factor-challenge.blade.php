@extends('reseller.layout')

@section('title', '2FA')

@section('content')
    <div class="rsl-2fa-narrow">
        <div class="rsl-panel rsl-panel-pad text-center">
            <h1 class="rsl-page-title">Two-factor code</h1>
            <p class="rsl-page-sub mt-2">Enter the 6-digit code from your app</p>
            <form method="post" action="{{ route('reseller.two-factor.verify') }}" class="mt-6">
                @csrf
                <input name="code" inputmode="numeric" autocomplete="one-time-code" required
                    class="rsl-input text-center text-lg tracking-widest" style="letter-spacing:0.3em">
                <button type="submit" class="rsl-btn w-full mt-4" style="width:100%;margin-top:1rem">Verify</button>
            </form>
        </div>
    </div>
@endsection
