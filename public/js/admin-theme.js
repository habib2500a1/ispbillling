/**
 * Admin theme: light | dark | system — synced with Filament Alpine store.
 */
(function () {
    const STORAGE_KEY = 'theme';
    const LEGACY_KEY = 'isp-admin-theme';

    function resolveTheme(mode) {
        if (mode === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        return mode === 'dark' ? 'dark' : 'light';
    }

    function getPreference() {
        return localStorage.getItem(STORAGE_KEY)
            || localStorage.getItem(LEGACY_KEY)
            || 'system';
    }

    function applyDom(mode) {
        const resolved = resolveTheme(mode);

        document.documentElement.setAttribute('data-theme', resolved);
        document.documentElement.dataset.themeMode = mode;
        document.documentElement.classList.toggle('dark', resolved === 'dark');

        return resolved;
    }

    function persist(mode) {
        localStorage.setItem(STORAGE_KEY, mode);
        try {
            localStorage.removeItem(LEGACY_KEY);
        } catch (e) {
            /* ignore */
        }
    }

    function notify(mode) {
        window.dispatchEvent(new CustomEvent('isp-theme-changed', { detail: { mode } }));
        window.dispatchEvent(new CustomEvent('theme-changed', { detail: mode }));
    }

    window.ispSetTheme = function (mode) {
        if (!['light', 'dark', 'system'].includes(mode)) {
            mode = 'system';
        }
        persist(mode);
        applyDom(mode);
        notify(mode);

        if (window.Alpine?.store) {
            try {
                const store = window.Alpine.store('theme');
                if (store !== undefined) {
                    window.Alpine.store(
                        'theme',
                        resolveTheme(mode),
                    );
                }
            } catch (e) {
                /* Alpine not ready */
            }
        }
    };

    window.ispGetTheme = function () {
        return getPreference();
    };

    window.ispGetResolvedTheme = function () {
        return resolveTheme(getPreference());
    };

    // Migrate legacy key once.
    const legacy = localStorage.getItem(LEGACY_KEY);
    if (legacy && !localStorage.getItem(STORAGE_KEY)) {
        localStorage.setItem(STORAGE_KEY, legacy);
    }

    applyDom(getPreference());

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (getPreference() === 'system') {
            applyDom('system');
            notify('system');
        }
    });

    document.addEventListener('livewire:navigated', () => {
        applyDom(getPreference());
    });

    window.addEventListener('theme-changed', (event) => {
        const mode = event.detail;
        if (!mode || !['light', 'dark', 'system'].includes(mode)) {
            return;
        }
        persist(mode);
        applyDom(mode);
        window.dispatchEvent(new CustomEvent('isp-theme-changed', { detail: { mode } }));
    });

    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('isp-open-command-palette'));
        }
    });

    function ispPurgeLocaleSwitcher() {
        document.querySelectorAll('.isp-mobile-bar__locales').forEach((el) => el.remove());

        document.querySelectorAll('a[href*="/locale/"]').forEach((link) => {
            const topbarGroup = link.closest('.isp-topbar-extras .rounded-lg.border');
            if (topbarGroup) {
                topbarGroup.remove();
                return;
            }

            if (link.closest('.isp-mobile-bar__locales, .portal-locale-bar')) {
                link.remove();
            }
        });

        document.querySelectorAll('.portal-locale-label, .portal-locale-bar__sep').forEach((el) => el.remove());

        document.querySelectorAll('.portal-locale-bar').forEach((bar) => {
            if (!bar.querySelector('a')) {
                bar.remove();
            }
        });
    }

    ispPurgeLocaleSwitcher();
    document.addEventListener('DOMContentLoaded', ispPurgeLocaleSwitcher);
    document.addEventListener('livewire:navigated', ispPurgeLocaleSwitcher);
})();
