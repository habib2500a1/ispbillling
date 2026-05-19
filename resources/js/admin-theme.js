/**
 * Theme: light | dark | system (auto)
 */
(function () {
    const STORAGE_KEY = 'isp-admin-theme';

    function resolveTheme(mode) {
        if (mode === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        return mode === 'dark' ? 'dark' : 'light';
    }

    function applyTheme(mode) {
        const resolved = resolveTheme(mode);
        document.documentElement.classList.toggle('dark', resolved === 'dark');
        document.documentElement.setAttribute('data-theme', resolved);
        localStorage.setItem(STORAGE_KEY, mode);
    }

    const saved = localStorage.getItem(STORAGE_KEY) || 'system';
    applyTheme(saved);

    window.ispSetTheme = function (mode) {
        applyTheme(mode);
        window.dispatchEvent(new CustomEvent('isp-theme-changed', { detail: { mode } }));
    };

    window.ispGetTheme = function () {
        return localStorage.getItem(STORAGE_KEY) || 'system';
    };

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (window.ispGetTheme() === 'system') {
            applyTheme('system');
        }
    });

    document.addEventListener('keydown', (e) => {
        if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
            e.preventDefault();
            window.dispatchEvent(new CustomEvent('isp-open-command-palette'));
        }
    });
})();
