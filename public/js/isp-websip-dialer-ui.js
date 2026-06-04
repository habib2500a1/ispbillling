(function () {
    'use strict';

    window.ispWebSipLiveCall = function (phone) {
        var target = String(phone || '').replace(/[^\d*#]/g, '');

        if (typeof window.ispWebSipOpenDialer === 'function') {
            window.ispWebSipOpenDialer(target);
            if (target && typeof window.ispWebSipDial === 'function') {
                window.setTimeout(function () {
                    window.ispWebSipDial(target);
                }, 600);
            }
            return;
        }

        var fab = document.querySelector('[data-isp-websip-fab]');
        if (fab) {
            fab.click();
            if (target && typeof window.ispWebSipDial === 'function') {
                window.setTimeout(function () {
                    window.ispWebSipDial(target);
                }, 800);
            }
            return;
        }

        if (target) {
            window.location.href = 'tel:' + target;
            return;
        }

        window.alert('লাইভ কল চালু নেই। Call center → SIP settings এ WebSIP চালু করুন।');
    };

    if (window.ispWebSipDialerUiBootstrapped) {
        return;
    }
    window.ispWebSipDialerUiBootstrapped = true;

    var panel = document.querySelector('[data-isp-websip-panel]');
    var backdrop = document.querySelector('[data-isp-websip-backdrop]');
    var fab = document.querySelector('[data-isp-websip-fab]');
    var closeBtn = document.querySelector('[data-isp-websip-close]');
    var input = document.querySelector('[data-isp-websip-input]');
    var display = document.querySelector('[data-isp-websip-display]');
    var dialBtn = document.querySelector('[data-isp-websip-dial-btn]');
    var backspaceBtn = document.querySelector('[data-isp-websip-backspace]');
    var volume = document.querySelector('[data-isp-websip-volume]');

    function getDigits() {
        return input ? String(input.value || '').replace(/\D+/g, '') : '';
    }

    function syncDisplay() {
        if (!display || !input) {
            return;
        }
        var raw = input.value || '';
        display.textContent = raw !== '' ? raw : '—';
    }

    function setDigits(value) {
        if (!input) {
            return;
        }
        input.value = String(value || '').replace(/[^\d*#]/g, '');
        syncDisplay();
    }

    function appendKey(key) {
        if (!input) {
            return;
        }
        input.value = (input.value || '') + key;
        syncDisplay();
    }

    function openPanel(prefill) {
        if (!panel) {
            return;
        }
        panel.classList.add('is-open');
        panel.setAttribute('aria-hidden', 'false');
        if (backdrop) {
            backdrop.classList.add('is-open');
            backdrop.setAttribute('aria-hidden', 'false');
        }
        document.body.classList.add('isp-websip-panel-open');
        if (prefill) {
            setDigits(prefill);
        }
        syncDisplay();
        var keypad = document.querySelector('[data-isp-websip-keypad]');
        if (keypad && typeof keypad.scrollIntoView === 'function') {
            window.requestAnimationFrame(function () {
                keypad.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            });
        }
    }

    function closePanel() {
        if (!panel) {
            return;
        }
        panel.classList.remove('is-open');
        panel.setAttribute('aria-hidden', 'true');
        if (backdrop) {
            backdrop.classList.remove('is-open');
            backdrop.setAttribute('aria-hidden', 'true');
        }
        document.body.classList.remove('isp-websip-panel-open');
    }

    function togglePanel(prefill) {
        if (panel && panel.classList.contains('is-open')) {
            closePanel();
        } else {
            openPanel(prefill || '');
        }
    }

    window.ispWebSipOpenDialer = openPanel;
    window.ispWebSipCloseDialer = closePanel;

    if (fab) {
        fab.addEventListener('click', function () {
            togglePanel('');
        });
    }

    if (closeBtn) {
        closeBtn.addEventListener('click', closePanel);
    }

    if (backdrop) {
        backdrop.addEventListener('click', closePanel);
    }

    document.querySelectorAll('[data-isp-websip-key]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            appendKey(btn.getAttribute('data-isp-websip-key') || '');
        });
    });

    if (backspaceBtn) {
        backspaceBtn.addEventListener('click', function () {
            if (!input) {
                return;
            }
            input.value = String(input.value || '').slice(0, -1);
            syncDisplay();
        });
    }

    if (dialBtn) {
        dialBtn.addEventListener('click', function () {
            if (window.ispWebSipDial) {
                window.ispWebSipDial(getDigits() || input?.value || '');
            }
        });
    }

    if (volume) {
        volume.addEventListener('input', function () {
            var audio = document.querySelector('audio');
            if (audio) {
                audio.volume = Number(volume.value) / 100;
            }
        });
    }

    var retryBtn = document.querySelector('[data-isp-websip-retry]');

    if (retryBtn) {
        retryBtn.addEventListener('click', function () {
            if (typeof window.ispWebSipRetryConnect === 'function') {
                window.ispWebSipRetryConnect();
            }
        });
    }

    document.addEventListener('isp-websip:registered', function () {
        if (fab) {
            fab.classList.add('isp-live-call-fab--ready');
        }
        if (retryBtn) {
            retryBtn.hidden = true;
        }
    });

    document.addEventListener('isp-websip:registration-failed', function () {
        if (retryBtn) {
            retryBtn.hidden = false;
        }
    });

    document.addEventListener('keydown', function (e) {
        if (!panel || !panel.classList.contains('is-open')) {
            return;
        }
        if (e.key >= '0' && e.key <= '9') {
            appendKey(e.key);
        } else if (e.key === '*' || e.key === '#') {
            appendKey(e.key);
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            if (input) {
                input.value = String(input.value || '').slice(0, -1);
                syncDisplay();
            }
        } else if (e.key === 'Enter' && window.ispWebSipDial) {
            window.ispWebSipDial(getDigits());
        } else if (e.key === 'Escape') {
            closePanel();
        }
    });
})();
