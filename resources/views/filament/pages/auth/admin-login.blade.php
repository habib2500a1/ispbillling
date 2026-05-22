<div class="isp-auth-card isp-auth-card--login">
    <h2 class="isp-auth-card__title">Login to your account</h2>
    <p class="isp-auth-card__sub">Admin panel · {{ config('isp.company_name') }}</p>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <form
        method="post"
        action="{{ route('admin.login.session') }}"
        class="mt-8 space-y-5"
        id="admin-login-form"
    >
        @csrf

        <div class="space-y-2">
            <label for="admin-login-email" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Username
            </label>
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
                tabindex="1"
                class="isp-auth-field-input fi-input block w-full rounded-lg border-gray-300 shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            />
        </div>

        <div class="space-y-2">
            <label for="admin-login-password" class="text-sm font-medium text-gray-700 dark:text-gray-300">
                Password
            </label>
            <input
                id="admin-login-password"
                name="password"
                type="password"
                required
                autocomplete="current-password"
                tabindex="2"
                class="isp-auth-field-input fi-input block w-full rounded-lg border-gray-300 shadow-sm transition focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-900 dark:text-white"
            />
        </div>

        <label class="flex cursor-pointer items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <input
                type="checkbox"
                name="remember"
                value="1"
                tabindex="3"
                {{ old('remember') ? 'checked' : '' }}
                class="rounded border-gray-300 text-primary-600 focus:ring-primary-500 dark:border-gray-600"
            />
            Remember me
        </label>

        @if ($errors->any())
            <div
                class="rounded-lg border border-danger-300 bg-danger-50 px-4 py-3 text-sm text-danger-800 dark:border-danger-800 dark:bg-danger-950/40 dark:text-danger-200"
                role="alert"
            >
                {{ $errors->first() }}
            </div>
        @endif

        <button
            type="submit"
            tabindex="4"
            class="fi-btn fi-btn-size-md relative grid w-full grid-flow-col items-center justify-center gap-1.5 rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm outline-none transition hover:bg-primary-500 focus-visible:ring-2 focus-visible:ring-primary-500/50 dark:bg-primary-500 dark:hover:bg-primary-400"
        >
            Sign in
        </button>
    </form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</div>
