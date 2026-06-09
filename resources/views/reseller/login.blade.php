@php
    $wl = app()->bound('reseller.white_label') ? app('reseller.white_label') : null;
    $companyName = $wl?->brand_name ?: \App\Support\CompanyBranding::name();
    $logoUrl = $wl?->logoUrl() ?: \App\Support\CompanyBranding::logoUrl();
@endphp

<x-auth.login-shell
    :companyName="$companyName"
    :logo="$logoUrl"
    eyebrow="Partner portal"
    lead="Sign in with partner ID, email, or phone"
    roleAccent="reseller"
    :portalEnabled="(bool) config('portal.enabled', true)"
    :resellerEnabled="true"
>
    @include('partials.demo-credentials-hint', ['demoHint' => 'reseller'])

    @if ($wl && filled($wl->portal_login_message))
        <div class="lh-note" role="status">{{ $wl->portal_login_message }}</div>
    @endif

    @if ($errors->any())
        <div class="lh-alert lh-alert--error" role="alert">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="post" action="{{ route('reseller.login.store') }}" class="lh-form">
        @csrf
        <div class="lh-field">
            <label for="login">Partner ID, email, or phone</label>
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
                placeholder="RSL-0001 or email"
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
                {{ (old('remember') !== null ? old('remember') : config('reseller_portal.session.remember_default', true)) ? 'checked' : '' }}
            >
            <span>Remember this device</span>
        </label>
        <button type="submit" class="lh-submit">Sign in</button>
    </form>
</x-auth.login-shell>
