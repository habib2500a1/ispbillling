/**
 * Recover from intermittent blank admin pages (failed Livewire / wire:navigate).
 */
(function () {
    const RELOAD_KEY = 'isp-admin-reload-at';
    const RELOAD_COOLDOWN_MS = 4000;

    function shouldReload() {
        const last = Number(sessionStorage.getItem(RELOAD_KEY) || 0);
        return Date.now() - last > RELOAD_COOLDOWN_MS;
    }

    function safeReload(reason) {
        if (!shouldReload()) {
            return;
        }

        sessionStorage.setItem(RELOAD_KEY, String(Date.now()));
        console.warn('[ISP Admin] Reloading:', reason);
        window.location.reload();
    }

    function isLivewireNavigateInit(init) {
        if (!init || !init.headers) {
            return false;
        }

        if (init.headers instanceof Headers) {
            return init.headers.has('X-Livewire-Navigate');
        }

        return init.headers['X-Livewire-Navigate'] !== undefined;
    }

    function pageLooksLikeAdmin(html) {
        return html.includes('fi-body')
            || html.includes('fi-layout')
            || html.includes('wire:');
    }

    const nativeFetch = window.fetch.bind(window);
    window.fetch = function (input, init) {
        return nativeFetch(input, init).then((response) => {
            if (!isLivewireNavigateInit(init)) {
                return response;
            }

            if (!response.ok) {
                safeReload('navigation HTTP ' + response.status);
                throw new Error('isp-navigate-aborted');
            }

            return response.clone().text().then((html) => {
                if (!pageLooksLikeAdmin(html)) {
                    safeReload('navigation returned invalid HTML');
                    throw new Error('isp-navigate-invalid-html');
                }

                return new Response(html, {
                    status: response.status,
                    statusText: response.statusText,
                    headers: response.headers,
                });
            });
        });
    };

    function mainContentIsEmpty() {
        if (document.querySelector('[wire\\:id]') || document.querySelector('.isp-optical-noc')) {
            return false;
        }

        if (document.querySelector('[wire\\:loading], .fi-loading-section')) {
            return false;
        }

        const main = document.querySelector('.fi-main')
            || document.querySelector('.fi-page')
            || document.querySelector('[role="main"]');

        if (!main) {
            return document.body && document.body.innerText.trim().length < 20;
        }

        return main.children.length === 0 && main.innerText.trim().length < 10;
    }

    document.addEventListener('DOMContentLoaded', () => {
        window.setTimeout(() => {
            if (document.querySelector('.fi-body') && mainContentIsEmpty()) {
                safeReload('empty admin content on first paint');
            }
        }, 8000);
    });

    document.addEventListener('livewire:navigated', () => {
        window.requestAnimationFrame(() => {
            if (mainContentIsEmpty()) {
                safeReload('empty content after navigation');
            }
        });
    });

    document.addEventListener('livewire:init', () => {
        if (!window.Livewire?.hook) {
            return;
        }

        window.Livewire.hook('request', ({ fail }) => {
            fail(({ status, preventDefault }) => {
                if (status === 419 || status === 401 || status === 403) {
                    preventDefault();
                    safeReload('session expired (HTTP ' + status + ')');
                }
            });
        });
    });
})();
