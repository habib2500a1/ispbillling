(function () {
    'use strict';

    if (window.ispWebSipBootstrapped) {
        return;
    }
    window.ispWebSipBootstrapped = true;

    var cfg = window.__ispWebSip || null;
    var ua = null;
    var activeSession = null;
    var activeCallMeta = null;
    var wssTryIndex = 0;
    var registrarTryIndex = 0;
    var connectTimeoutId = null;
    var pendingDialNumber = null;
    var registrationWaiters = [];

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');

        return meta ? meta.getAttribute('content') : '';
    }

    function logCallToServer(payload) {
        if (!cfg || !cfg.log_url) {
            return;
        }

        fetch(cfg.log_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                Accept: 'application/json',
                'X-CSRF-TOKEN': csrfToken(),
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            body: JSON.stringify(payload),
        })
            .then(function (res) {
                if (!res.ok) {
                    throw new Error('HTTP ' + res.status);
                }

                return res.json();
            })
            .then(function () {
                var hint = document.querySelector('[data-isp-websip-mode-hint]');
                if (hint) {
                    hint.textContent = 'Last call saved to Call logs';
                }
            })
            .catch(function (err) {
                console.warn('WebSIP call log save failed', err);
            });
    }

    function startCallMeta(phone) {
        activeCallMeta = {
            phone: phone,
            startedAt: new Date().toISOString(),
            externalId:
                'websip-' +
                Date.now() +
                '-' +
                Math.random().toString(36).slice(2, 11),
        };
    }

    function finishCallMeta(status, cause) {
        if (!activeCallMeta) {
            return;
        }

        var started = new Date(activeCallMeta.startedAt);
        var duration = Math.max(
            0,
            Math.floor((Date.now() - started.getTime()) / 1000),
        );

        logCallToServer({
            phone: activeCallMeta.phone,
            status: status,
            duration_seconds: duration,
            started_at: activeCallMeta.startedAt,
            external_id: activeCallMeta.externalId,
            cause: cause || null,
            direction: 'outbound',
        });

        activeCallMeta = null;
    }

    function wssUriList() {
        if (!cfg) {
            return [];
        }
        if (Array.isArray(cfg.wss_uris) && cfg.wss_uris.length) {
            return cfg.wss_uris;
        }
        if (cfg.wss_uri) {
            return [cfg.wss_uri];
        }
        return [];
    }

    function registrarList() {
        if (!cfg) {
            return [];
        }
        if (Array.isArray(cfg.registrar_servers) && cfg.registrar_servers.length) {
            return cfg.registrar_servers;
        }
        if (cfg.registrar_server) {
            return [cfg.registrar_server];
        }
        var host = cfg.identity_host || cfg.sip_domain || cfg.sip_server || '';
        return host ? ['sip:' + host] : [];
    }

    function identityHost() {
        if (!cfg) {
            return '';
        }
        return cfg.identity_host || cfg.sip_domain || cfg.sip_server || '';
    }

    function setStatus(text, tone) {
        var el = document.querySelector('[data-isp-websip-status]');
        if (!el) {
            return;
        }
        el.textContent = text;
        el.className = 'isp-websip-panel__status';
        if (tone === 'ok') {
            el.classList.add('is-ok');
        } else if (tone === 'err') {
            el.classList.add('is-err');
        }
    }

    function updateModeHint(text) {
        var hint = document.querySelector('[data-isp-websip-mode-hint]');
        if (hint && text) {
            hint.textContent = text;
        }
    }

    function normalizeDialNumber(raw) {
        var digits = String(raw || '').replace(/\D+/g, '');
        if (!digits) {
            return '';
        }
        var cc = String((cfg && cfg.country_code) || '880').replace(/\D+/g, '');
        if (digits.length === 10 && cc === '880') {
            return '0' + digits;
        }
        if (digits.length === 11 && digits.charAt(0) === '0') {
            return digits;
        }
        if (digits.length === 13 && digits.indexOf('880') === 0) {
            return '0' + digits.slice(3);
        }
        return digits;
    }

    function clearConnectTimeout() {
        if (connectTimeoutId) {
            clearTimeout(connectTimeoutId);
            connectTimeoutId = null;
        }
    }

    function scheduleConnectTimeout() {
        clearConnectTimeout();
        var ms = (cfg && cfg.wss_connect_timeout_ms) || 8000;
        connectTimeoutId = setTimeout(function () {
            if (ua && ua.isRegistered && ua.isRegistered()) {
                return;
            }
            console.warn('WebSIP WSS timeout', wssUriList()[wssTryIndex]);
            stopUa();
            registrarTryIndex = 0;
            wssTryIndex += 1;
            bootJsSip();
        }, ms);
    }

    function notifyRegistrationWaiters(success) {
        var waiters = registrationWaiters.slice();
        registrationWaiters = [];
        waiters.forEach(function (cb) {
            try {
                cb(!!success);
            } catch (e) {
                console.warn(e);
            }
        });
    }

    function waitForRegistration(callback) {
        if (ua && ua.isRegistered && ua.isRegistered()) {
            callback(true);
            return;
        }
        registrationWaiters.push(callback);
        var ms = (cfg && cfg.register_wait_ms) || 20000;
        window.setTimeout(function () {
            var idx = registrationWaiters.indexOf(callback);
            if (idx === -1) {
                return;
            }
            registrationWaiters.splice(idx, 1);
            callback(ua && ua.isRegistered && ua.isRegistered());
        }, ms);
    }

    function stopUa() {
        clearConnectTimeout();
        if (!ua) {
            return;
        }
        try {
            ua.stop();
        } catch (e) {
            /* ignore */
        }
        ua = null;
        activeSession = null;
    }

    function setCallButtonActive(active) {
        var btn = document.querySelector('[data-isp-websip-dial-btn]');
        if (btn) {
            btn.classList.toggle('is-active', !!active);
        }
    }

    function placeCall(target) {
        if (!ua || !ua.isRegistered || !ua.isRegistered()) {
            return false;
        }

        try {
            if (activeSession) {
                activeSession.terminate();
                activeSession = null;
                setCallButtonActive(false);
            }
            var host = identityHost();
            var sipUri = 'sip:' + encodeURIComponent(target) + '@' + host;
            startCallMeta(target);
            activeSession = ua.call(sipUri, {
                mediaConstraints: { audio: true, video: false },
                pcConfig: {
                    iceServers: [{ urls: 'stun:stun.l.google.com:19302' }],
                },
            });
            setCallButtonActive(true);
            setStatus('Calling ' + target + '…', 'ok');
            activeSession.on('ended', function () {
                finishCallMeta('answered', null);
                activeSession = null;
                setCallButtonActive(false);
                setStatus('Call ended — saved to logs', 'ok');
                window.setTimeout(function () {
                    if (ua && ua.isRegistered && ua.isRegistered()) {
                        setStatus('Ready — registered', 'ok');
                    }
                }, 2500);
            });
            activeSession.on('failed', function (e) {
                var cause = (e && e.cause) ? String(e.cause) : 'call failed';
                finishCallMeta('failed', cause);
                activeSession = null;
                setCallButtonActive(false);
                setStatus('Call failed — ' + cause, 'err');
            });
            document.dispatchEvent(
                new CustomEvent('isp-websip:dial', { detail: { number: target } }),
            );
            return true;
        } catch (e) {
            console.warn('WebSIP dial failed', e);
            setStatus('Dial failed', 'err');
            return false;
        }
    }

    window.ispWebSipDial = function (number) {
        var target = normalizeDialNumber(number);
        if (!target) {
            return;
        }

        if (typeof window.ispWebSipOpenDialer === 'function') {
            window.ispWebSipOpenDialer(target);
        }

        if (!cfg || !cfg.configured) {
            setStatus('SIP settings incomplete', 'err');
            return;
        }

        if (ua && ua.isRegistered && ua.isRegistered()) {
            placeCall(target);
            return;
        }

        pendingDialNumber = target;
        setStatus('SIP connecting…', null);
        updateModeHint('WSS + register চলছে — Ready হলে কল যাবে।');

        if (!ua) {
            loadJsSipLibrary(0);
        }

        waitForRegistration(function (ok) {
            if (pendingDialNumber !== target) {
                return;
            }
            pendingDialNumber = null;
            if (ok) {
                placeCall(target);
                return;
            }
            setStatus('Not registered — WSS URI বা PortSIP', 'err');
            var settingsHint = cfg.settings_url
                ? ' SIP settings → WSS URI (BDWebs থেকে নিন)।'
                : '';
            updateModeHint(
                'PortSIP অ্যাপ UDP 5060-এ কাজ করে। ব্রাউজারে WSS লাগে।' + settingsHint,
            );
            document.dispatchEvent(new CustomEvent('isp-websip:registration-failed'));
        });
    };

    window.ispWebSipRetryConnect = function () {
        if (!cfg || !cfg.configured) {
            return;
        }
        wssTryIndex = 0;
        registrarTryIndex = 0;
        stopUa();
        setStatus('Reconnecting…', null);
        bootJsSip();
    };

    if (!cfg || !cfg.enabled) {
        return;
    }

    function bootJsSip() {
        if (!cfg.configured || typeof window.JsSIP === 'undefined') {
            return;
        }

        var uris = wssUriList();
        var registrars = registrarList();
        var host = identityHost();

        if (!uris.length || !registrars.length || !host) {
            setStatus('Set SIP server + domain (PortSIP same)', 'err');
            return;
        }

        if (wssTryIndex >= uris.length) {
            setStatus('WSS নেই — PortSIP অ্যাপ ব্যবহার করুন', 'err');
            updateModeHint(
                'Browser WSS failed. Use PortSIP app or add WSS URL in SIP settings.',
            );
            notifyRegistrationWaiters(false);
            document.dispatchEvent(new CustomEvent('isp-websip:registration-failed'));
            return;
        }

        if (registrarTryIndex >= registrars.length) {
            registrarTryIndex = 0;
            wssTryIndex += 1;
            if (wssTryIndex >= uris.length) {
                setStatus('Register failed — SIP password/domain', 'err');
                updateModeHint('Check PortSIP password & domain in SIP settings.');
                notifyRegistrationWaiters(false);
                document.dispatchEvent(new CustomEvent('isp-websip:registration-failed'));
                return;
            }
        }

        var wssUri = uris[wssTryIndex];
        var registrar = registrars[registrarTryIndex];
        stopUa();

        try {
            var socket = new JsSIP.WebSocketInterface(wssUri);
            var sipUserUri = 'sip:' + cfg.username + '@' + host;
            var options = {
                sockets: [socket],
                uri: sipUserUri,
                authorization_user: cfg.username,
                password: cfg.password,
                display_name: cfg.display_name || cfg.username,
                session_timers: false,
                register: true,
                registrar_server: registrar,
            };

            ua = new JsSIP.UA(options);

            ua.on('connected', function () {
                clearConnectTimeout();
                setStatus('WSS OK — registering…', null);
            });

            ua.on('registered', function () {
                clearConnectTimeout();
                setStatus('Ready — registered (browser)', 'ok');
                updateModeHint(
                    'Same PortSIP login · WSS ' +
                        (wssTryIndex + 1) +
                        '/' +
                        uris.length,
                );
                notifyRegistrationWaiters(true);
                document.dispatchEvent(new CustomEvent('isp-websip:registered', {
                    detail: { wss_uri: wssUri, registrar: registrar },
                }));
                if (pendingDialNumber) {
                    var queued = pendingDialNumber;
                    pendingDialNumber = null;
                    placeCall(queued);
                }
            });
            ua.on('registrationFailed', function (e) {
                console.warn('WebSIP registration failed', wssUri, registrar, e);
                registrarTryIndex += 1;
                stopUa();
                if (registrarTryIndex < registrars.length) {
                    setStatus('Trying registrar ' + (registrarTryIndex + 1) + '/' + registrars.length + '…', null);
                    bootJsSip();
                    return;
                }
                registrarTryIndex = 0;
                wssTryIndex += 1;
                if (wssTryIndex < uris.length) {
                    setStatus('Trying WSS ' + (wssTryIndex + 1) + '/' + uris.length + '…', null);
                    bootJsSip();
                    return;
                }
                var cause = (e && e.cause) ? String(e.cause) : 'registration failed';
                setStatus('Register failed: ' + cause, 'err');
                updateModeHint(
                    'Try PortSIP mobile app (UDP 5060) or ask provider for WSS URL.',
                );
                notifyRegistrationWaiters(false);
                document.dispatchEvent(new CustomEvent('isp-websip:registration-failed'));
            });
            ua.on('disconnected', function () {
                if (!ua || (ua.isRegistered && ua.isRegistered())) {
                    return;
                }
            });

            ua.start();
            scheduleConnectTimeout();
            setStatus(
                'PortSIP connect… WSS ' + (wssTryIndex + 1) + '/' + uris.length,
                null,
            );
        } catch (e) {
            console.warn('WebSIP init failed', wssUri, e);
            registrarTryIndex += 1;
            bootJsSip();
        }
    }

    var jssipScriptSources = [
        '/vendor/jssip/jssip.min.js?v=3.10.10',
        'https://cdn.jsdelivr.net/npm/jssip/dist/jssip.min.js',
    ];

    function loadJsSipLibrary(index) {
        if (typeof window.JsSIP !== 'undefined') {
            bootJsSip();
            return;
        }

        if (index >= jssipScriptSources.length) {
            setStatus('JsSIP load failed — refresh page', 'err');
            updateModeHint(
                'SIP library could not load. Hard refresh (Ctrl+Shift+R) or check admin /vendor/jssip/.',
            );
            return;
        }

        var script = document.createElement('script');
        script.src = jssipScriptSources[index];
        script.async = true;
        script.onload = function () {
            if (typeof window.JsSIP === 'undefined') {
                loadJsSipLibrary(index + 1);
                return;
            }
            bootJsSip();
        };
        script.onerror = function () {
            loadJsSipLibrary(index + 1);
        };
        document.head.appendChild(script);
    }

    if (cfg && cfg.configured) {
        loadJsSipLibrary(0);
    } else if (cfg) {
        setStatus('PortSIP password in SIP settings', null);
    }
})();
