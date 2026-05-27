@extends('portal.layout')

@section('title', 'Verify code')

@section('content')
    <div class="portal-auth-shell portal-auth-shell--split">
        <section class="portal-auth-panel">
            <div class="portal-auth-hero">
                <p class="portal-auth-hero__eyebrow">Verify sign-in</p>
                <h1 class="portal-auth-hero__title">Check your email</h1>
                <p class="portal-auth-hero__sub">Enter the one-time code we sent to finish signing in. If you do not see the email, check spam or contact your provider.</p>
            </div>

            @if (session('portal_session_expired'))
                <div class="portal-note-banner mt-4" role="alert">
                    Your session expired. <a href="{{ route('portal.login', ['abandon' => 1]) }}" class="portal-link">Sign in again</a>.
                </div>
            @endif

            <form method="post" action="{{ route('portal.login.otp.verify') }}" class="portal-auth-form">
                @csrf
                <div>
                    <label for="code">One-time code</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        autocomplete="one-time-code"
                        required
                        autofocus
                        maxlength="12"
                        class="portal-auth-code"
                        placeholder="123456"
                    />
                </div>
                <div class="portal-auth-actions">
                    <button type="submit" class="portal-btn-primary w-full">Continue</button>
                </div>
            </form>

            <p class="mt-4 text-center text-sm text-slate-500">
                <a href="{{ route('portal.login', ['abandon' => '1']) }}" class="portal-link">Start over</a>
            </p>
        </section>

        <aside class="portal-auth-panel">
            <div class="portal-auth-points">
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Why this extra step?</p>
                    <p class="portal-auth-point__body">The one-time code helps protect your account from password-only access and keeps billing and service data safer.</p>
                </div>
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Code not arriving?</p>
                    <p class="portal-auth-point__body">Wait a moment, check spam or promotions, then restart sign-in if the code expires.</p>
                </div>
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Still blocked?</p>
                    <p class="portal-auth-point__body">If your email is outdated or inaccessible, contact your provider so they can update your account details.</p>
                </div>
            </div>
        </aside>
    </div>
@endsection
