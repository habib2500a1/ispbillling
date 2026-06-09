/**
 * Bill payment page theme — single source of truth (light | dark only).
 */
(function () {
    const KEY = 'isp-portal-theme';

    function apply(mode) {
        const resolved = mode === 'dark' ? 'dark' : 'light';
        const html = document.documentElement;
        html.classList.toggle('portal-dark', resolved === 'dark');
        html.setAttribute('data-portal-theme', resolved);
        localStorage.setItem(KEY, resolved);
        syncUi();
        window.dispatchEvent(new CustomEvent('portal-theme-changed', { detail: { mode: resolved } }));
    }

    function syncUi() {
        const dark = document.documentElement.getAttribute('data-portal-theme') === 'dark';
        const btn = document.getElementById('bp-theme-btn');
        const label = document.getElementById('bp-theme-label');
        const icon = document.getElementById('bp-theme-icon');

        if (icon) {
            icon.textContent = dark ? '🌙' : '☀️';
        }

        if (label) {
            label.textContent = dark ? 'Dark mode' : 'Light mode';
        }

        if (btn) {
            btn.setAttribute('aria-pressed', dark ? 'true' : 'false');
            btn.setAttribute(
                'title',
                dark ? 'Dark mode on — tap for light' : 'Light mode on — tap for dark',
            );
        }
    }

    window.portalSetTheme = apply;
    window.portalGetTheme = function () {
        return document.documentElement.getAttribute('data-portal-theme') === 'dark' ? 'dark' : 'light';
    };
    window.portalCycleTheme = function () {
        apply(window.portalGetTheme() === 'dark' ? 'light' : 'dark');
    };

    const stored = localStorage.getItem(KEY);
    apply(stored === 'dark' ? 'dark' : 'light');
})();
