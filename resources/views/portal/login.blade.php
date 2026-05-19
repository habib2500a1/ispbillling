@extends('portal.layout')

@section('title', __('portal.login_title'))

@section('content')
    <div class="portal-auth-card mx-auto max-w-md text-center">
        <div class="portal-auth-brand">
            @include('portal.partials.brand-mark')
        </div>
        <h1 class="portal-auth-title">{{ $companyName }}</h1>
        <p class="portal-auth-sub">{{ __('portal.customer_portal') }} · {{ __('portal.login_hint') }}</p>
        @if ($portalOtpEnabled ?? false)
            <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-sm text-amber-900 ring-1 ring-amber-200">
                Two-step login: you will enter a code sent to your email after your password.
            </p>
        @endif

        @if (session('portal_session_expired'))
            <p class="mt-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-950 ring-1 ring-amber-200" role="alert">
                Your login session expired (page was open too long or cookies were blocked). Please sign in again.
            </p>
        @endif

        <form method="post" action="{{ route('portal.login.store') }}" class="mt-8 space-y-4 text-left">
            @csrf
            <div>
                <label for="login" class="block text-sm font-semibold text-slate-700">Customer code, phone, or email</label>
                <input id="login" name="login" type="text" value="{{ old('login') }}" required autofocus autocomplete="username"
                    class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-200">
            </div>
            <div>
                <label for="password" class="block text-sm font-semibold text-slate-700">Password</label>
                <input id="password" name="password" type="password" required autocomplete="current-password"
                    class="mt-1 block w-full rounded-xl border border-slate-300 px-4 py-3 text-sm shadow-sm focus:border-violet-500 focus:ring-2 focus:ring-violet-200">
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-600">
                <input name="remember" type="checkbox" value="1" class="rounded border-slate-300 text-violet-600" {{ old('remember') ? 'checked' : '' }}>
                {{ __('portal.remember_device') }}
            </label>
            <button type="submit" class="portal-btn-primary w-full py-3 text-base">{{ __('portal.login') }}</button>
        </form>

        @if (config('portal.signup.enabled', true))
            <p class="mt-6 text-center text-sm text-slate-600">
                {{ __('portal.new_customer') }}
                <a href="{{ route('portal.signup') }}" class="font-semibold text-violet-600 hover:underline">{{ __('portal.request_connection') }}</a>
            </p>
        @endif
    </div>
@endsection
