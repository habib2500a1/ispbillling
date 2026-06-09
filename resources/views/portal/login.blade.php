<x-auth.login-shell
    :companyName="$companyName"
    :logo="$companyLogo ?? null"
    eyebrow="Customer portal"
    :lead="! empty($whiteLabelReseller)
        ? $companyName.' customer portal · Sign in with your account'
        : __('portal.customer_portal').' · '.__('portal.login_hint')"
    roleAccent="customer"
    :portalEnabled="true"
    :resellerEnabled="(bool) config('reseller_portal.enabled', true)"
>
    @include('partials.demo-credentials-hint', ['demoHint' => 'customer'])

    @if ($portalOtpEnabled ?? false)
        <div class="lh-note" role="status">
            Two-step login is enabled. After your password, you will enter a code sent to your email.
        </div>
    @endif

    @if (session('portal_session_expired'))
        <div class="lh-alert lh-alert--error" role="alert">
            Your login session expired because the page stayed open too long or cookies were blocked. Please sign in again.
        </div>
    @endif

    @if ($errors->any())
        <div class="lh-alert lh-alert--error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="post" action="{{ route('portal.login.store') }}" class="lh-form">
        @csrf
        <div class="lh-field">
            <label for="login">Customer code, phone, or email</label>
            <input
                id="login"
                name="login"
                type="text"
                value="{{ old('login') }}"
                required
                autofocus
                autocomplete="username"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                placeholder="CUST-001, 01XXXXXXXXX, or email"
            >
        </div>
        <div class="lh-field">
            <label for="password">Password</label>
            <div class="lh-password-wrap">
                <input
                    id="password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your portal password"
                >
                <button type="button" class="lh-password-toggle" aria-label="Show password" tabindex="-1">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                </button>
            </div>
        </div>
        <label class="lh-remember">
            <input
                name="remember"
                type="checkbox"
                value="1"
                {{ (old('remember') !== null ? old('remember') : config('portal.session.remember_default', true)) ? 'checked' : '' }}
            >
            <span>{{ __('portal.remember_device') }}</span>
        </label>
        <button type="submit" class="lh-submit">{{ __('portal.login') }}</button>
    </form>

    @if (config('portal.signup.enabled', true))
        <p class="lh-extra">
            {{ __('portal.new_customer') }}
            <a href="{{ route('portal.signup') }}" class="lh-link">{{ __('portal.request_connection') }}</a>
        </p>
    @endif
</x-auth.login-shell>
