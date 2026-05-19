<div
    class="isp-topbar-extras flex shrink-0 items-center gap-2"
    x-data="{
        theme: window.ispGetTheme?.() || 'system',
        setTheme(mode) {
            window.ispSetTheme?.(mode);
            this.theme = mode;
        },
    }"
    @isp-theme-changed.window="theme = $event.detail.mode"
>
    <div
        class="isp-theme-switch"
        role="group"
        aria-label="Color theme"
    >
        <button
            type="button"
            class="isp-theme-switch__btn"
            title="Light mode"
            :class="{ 'isp-theme-switch__btn--active': theme === 'light' }"
            @click="setTheme('light')"
            aria-pressed="false"
            x-bind:aria-pressed="theme === 'light' ? 'true' : 'false'"
        >
            <x-filament::icon icon="heroicon-m-sun" class="h-4 w-4" />
        </button>
        <button
            type="button"
            class="isp-theme-switch__btn"
            title="Dark mode"
            :class="{ 'isp-theme-switch__btn--active': theme === 'dark' }"
            @click="setTheme('dark')"
            x-bind:aria-pressed="theme === 'dark' ? 'true' : 'false'"
        >
            <x-filament::icon icon="heroicon-m-moon" class="h-4 w-4" />
        </button>
        <button
            type="button"
            class="isp-theme-switch__btn"
            title="System theme"
            :class="{ 'isp-theme-switch__btn--active': theme === 'system' }"
            @click="setTheme('system')"
            x-bind:aria-pressed="theme === 'system' ? 'true' : 'false'"
        >
            <x-filament::icon icon="heroicon-m-computer-desktop" class="h-4 w-4" />
        </button>
    </div>
    <button
        type="button"
        class="isp-theme-btn text-xs font-semibold text-gray-600 dark:text-gray-300"
        title="Smart search (Ctrl+K)"
        @click="window.dispatchEvent(new CustomEvent('isp-open-command-palette'))"
    >
        ⌘K
    </button>
    @php
        $currentLocale = app()->getLocale();
        $localeLabels = config('locales.labels', []);
    @endphp
    <div class="flex items-center gap-1 rounded-lg border border-gray-200 bg-white/80 px-1 dark:border-gray-600 dark:bg-gray-900/80">
        @foreach (config('locales.supported', ['en']) as $code)
            <a
                href="{{ route('locale.switch', $code) }}"
                class="rounded px-2 py-1 text-xs font-semibold {{ $currentLocale === $code ? 'bg-teal-100 text-teal-800 dark:bg-teal-900/50 dark:text-teal-200' : 'text-gray-500 hover:text-gray-800 dark:text-gray-400' }}"
                title="{{ $localeLabels[$code] ?? $code }}"
            >{{ strtoupper($code) }}</a>
        @endforeach
    </div>
</div>
