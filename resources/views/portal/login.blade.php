@extends('portal.layout')

@section('title', __('portal.login_title'))

@section('content')
    <div class="portal-premium-orbs isp-premium-orbs" aria-hidden="true">
        <span></span><span></span><span></span>
    </div>
    <div class="portal-auth-shell portal-auth-shell--split">
        <section class="portal-auth-panel isp-gradient-border">
            <div class="isp-gradient-border__inner p-6 sm:p-8">
            <div class="portal-auth-brand">
                @include('portal.partials.brand-mark')
            </div>
            <h1 class="portal-auth-title">{{ $companyName }}</h1>
            <p class="portal-auth-sub">
                @if (! empty($whiteLabelReseller))
                    {{ $companyName }} customer portal · Sign in with your account
                @else
                    {{ __('portal.customer_portal') }} · {{ __('portal.login_hint') }}
                @endif
            </p>

            @if ($portalOtpEnabled ?? false)
                <div class="portal-note-banner portal-auth-note-spaced">
                    Two-step login is enabled. After your password, you will enter a code sent to your email.
                </div>
            @endif

            @if (session('portal_session_expired'))
                <div class="portal-note-banner portal-auth-note-spaced" role="alert">
                    Your login session expired because the page stayed open too long or cookies were blocked. Please sign in again.
                </div>
            @endif

            <form method="post" action="{{ route('portal.login.store') }}" class="portal-auth-form">
                @csrf
                <div>
                    <label for="login">Customer code, phone, or email</label>
                    <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username" placeholder="CUST-001, 01XXXXXXXXX, or email">
                </div>
                <div>
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="Enter your portal password">
                </div>
                <label class="portal-auth-remember">
                    <input name="remember" type="checkbox" value="1" class="portal-auth-checkbox" {{ old('remember') ? 'checked' : '' }}>
                    {{ __('portal.remember_device') }}
                </label>
                <div class="portal-auth-actions">
                    <button type="submit" class="portal-btn-primary portal-btn-primary--block">{{ __('portal.login') }}</button>
                </div>
            </form>

            @if (config('portal.signup.enabled', true))
                <div class="portal-auth-divider">New here?</div>
                <p class="portal-auth-footer-text">
                    {{ __('portal.new_customer') }}
                    <a href="{{ route('portal.signup') }}" class="portal-link">{{ __('portal.request_connection') }}</a>
                </p>
            @endif

            <x-mobile-app-promo variant="compact" class="portal-auth-note-spaced" />
            </div>
        </section>

        <aside class="portal-auth-panel">
            <div class="portal-auth-hero">
                <p class="portal-auth-hero__eyebrow">Customer access</p>
                <h2 class="portal-auth-hero__title">
                    @if (! empty($whiteLabelReseller))
                        Welcome to {{ $companyName }}
                    @else
                        One portal for bills, usage, ONU, and support
                    @endif
                </h2>
                <p class="portal-auth-hero__sub">
                    @if (! empty($whiteLabelReseller))
                        Pay bills, check usage, open support tickets, and manage your connection from one place.
                    @else
                        Sign in to check live service data, pay bills, create tickets, and manage your customer profile from any device.
                    @endif
                </p>
            </div>

            <div class="portal-auth-points">
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Billing and invoices</p>
                    <p class="portal-auth-point__body">Track outstanding balance, invoice history, and payment records from one dashboard.</p>
                </div>
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Real-time service insight</p>
                    <p class="portal-auth-point__body">View live usage, optical signal, equipment status, and quick diagnostic tools.</p>
                </div>
                <div class="portal-auth-point">
                    <p class="portal-auth-point__title">Support continuity</p>
                    <p class="portal-auth-point__body">Tickets and live chat stay connected, so your issue history remains preserved.</p>
                </div>
            </div>
        </aside>
    </div>
@endsection
