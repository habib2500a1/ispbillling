<x-auth.login-shell
    :companyName="$companyName"
    :logo="$companyLogo ?? null"
    eyebrow="Staff access"
    lead="Admin panel · sign in with your staff email or username"
    roleAccent="staff"
    :portalEnabled="(bool) config('portal.enabled', true)"
    :resellerEnabled="(bool) config('reseller_portal.enabled', true)"
>
    @include('partials.demo-credentials-hint', ['demoHint' => 'admin'])

    @if ($errors->any())
        <div class="lh-alert lh-alert--error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    @if (session('status'))
        <div class="lh-note" role="status">{{ session('status') }}</div>
    @endif

    <form method="post" action="{{ route('admin.login.session') }}" class="lh-form" id="admin-login-form">
        @csrf
        <div class="lh-field">
            <label for="admin-login-email">Email or username</label>
            <input
                id="admin-login-email"
                name="email"
                type="text"
                value="{{ old('email') }}"
                required
                autofocus
                autocomplete="username"
                autocapitalize="off"
                autocorrect="off"
                spellcheck="false"
                placeholder="staff@company.com"
            >
        </div>
        <div class="lh-field">
            <label for="admin-login-password">Password</label>
            <div class="lh-password-wrap">
                <input
                    id="admin-login-password"
                    name="password"
                    type="password"
                    required
                    autocomplete="current-password"
                    placeholder="Enter your password"
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
                type="checkbox"
                name="remember"
                value="1"
                {{ (old('remember') !== null ? old('remember') : config('auth_session.remember_default', true)) ? 'checked' : '' }}
            >
            <span>Remember this device</span>
        </label>
        <button type="submit" class="lh-submit">Sign in</button>
    </form>
</x-auth.login-shell>
