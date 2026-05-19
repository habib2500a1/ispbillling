/**
 * Customer portal theme: light | dark | system
 */
(function () {
    const KEY = 'isp-portal-theme';

    function resolve(mode) {
        if (mode === 'system') {
            return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        }

        return mode === 'dark' ? 'dark' : 'light';
    }

    function apply(mode) {
        const resolved = resolve(mode);
        document.documentElement.classList.toggle('portal-dark', resolved === 'dark');
        document.documentElement.setAttribute('data-portal-theme', resolved);
        localStorage.setItem(KEY, mode);
    }

    apply(localStorage.getItem(KEY) || 'system');

    window.portalSetTheme = function (mode) {
        apply(mode);
        window.dispatchEvent(new CustomEvent('portal-theme-changed', { detail: { mode } }));
    };

    window.portalGetTheme = function () {
        return localStorage.getItem(KEY) || 'system';
    };

    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', () => {
        if (window.portalGetTheme() === 'system') {
            apply('system');
        }
    });
})();
