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
        class="isp-topbar-menu-search inline-flex items-center gap-1 rounded-lg border border-gray-200 bg-white/80 px-2.5 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900/80 dark:text-gray-300"
        title="Open full menu"
        x-show="! $store.sidebar.isOpen"
        x-cloak
        @click="$store.sidebar.open(); window.dispatchEvent(new CustomEvent('isp-focus-sidebar-menu-search'));"
    >
        <x-filament::icon icon="heroicon-m-bars-3-bottom-left" class="h-4 w-4" />
        Menu
    </button>
    <button
        type="button"
        class="isp-theme-btn text-xs font-semibold text-gray-600 dark:text-gray-300"
        title="Smart search (Ctrl+K)"
        @click="window.dispatchEvent(new CustomEvent('isp-open-command-palette'))"
    >
        ⌘K
    </button>
</div>
