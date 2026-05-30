/**
 * Portal full speed test: ping, download, upload against ISP portal endpoints.
 */
(function () {
    const panel = document.getElementById('speed-test-panel');
    if (!panel) {
        return;
    }

    const pingUrl = panel.dataset.pingUrl;
    const downUrl = panel.dataset.downloadUrl;
    const upUrl = panel.dataset.uploadUrl;
    const uploadBytes = Math.max(65536, parseInt(panel.dataset.uploadBytes || '262144', 10));
    let csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    const els = {
        ping: document.getElementById('st-ping'),
        down: document.getElementById('st-down'),
        up: document.getElementById('st-up'),
        run: document.getElementById('st-run'),
        status: document.getElementById('st-status'),
        ring: document.getElementById('st-ring'),
        ringValue: document.getElementById('st-ring-value'),
    };

    const stages = {
        ping: document.getElementById('stage-ping'),
        down: document.getElementById('stage-down'),
        up: document.getElementById('stage-up'),
    };

    const fetchOpts = { cache: 'no-store', credentials: 'same-origin' };

    function resetStages() {
        Object.values(stages).forEach((el) => {
            if (el) {
                el.className = 'portal-test-stage__item';
            }
        });
    }

    function setStage(name, state) {
        const el = stages[name];
        if (!el) {
            return;
        }
        el.className =
            'portal-test-stage__item' +
            (state === 'active' ? ' is-active' : state === 'done' ? ' is-done' : '');
    }

    function setRing(label) {
        if (els.ringValue) {
            els.ringValue.textContent = label;
        }
    }

    function failStatus(step, httpStatus) {
        const hint =
            httpStatus === 419
                ? 'Session expired — refresh the page and try again.'
                : httpStatus === 413
                  ? 'Upload too large for server limits.'
                  : 'Check connection and try again.';
        if (els.status) {
            els.status.textContent = step + ' failed (' + hint + ')';
        }
        setRing('—');
        resetStages();
    }

    async function fetchOk(url, options = {}) {
        const res = await fetch(url, { ...fetchOpts, ...options });
        if (!res.ok) {
            const err = new Error('HTTP ' + res.status);
            err.status = res.status;
            throw err;
        }

        return res;
    }

    async function measurePing(samples = 3) {
        const times = [];
        for (let i = 0; i < samples; i++) {
            const t0 = performance.now();
            const res = await fetchOk(pingUrl + '?_=' + Date.now(), {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await res.json();
            if (data.csrf_token) {
                csrf = data.csrf_token;
                const meta = document.querySelector('meta[name="csrf-token"]');
                if (meta) {
                    meta.setAttribute('content', csrf);
                }
            }
            if (typeof data.server_time !== 'number') {
                throw new Error('Invalid ping response');
            }
            times.push(performance.now() - t0);
        }
        times.sort((a, b) => a - b);

        return times[Math.floor(times.length / 2)];
    }

    async function measureDownload() {
        const t0 = performance.now();
        const res = await fetchOk(downUrl + '?_=' + Date.now());
        const blob = await res.blob();
        const expected = parseInt(res.headers.get('Content-Length') || '0', 10);
        if (expected > 0 && blob.size < expected * 0.5) {
            const err = new Error('Incomplete download');
            err.status = 0;
            throw err;
        }
        const sec = Math.max((performance.now() - t0) / 1000, 0.001);

        return (blob.size * 8) / sec / 1_000_000;
    }

    async function measureUpload() {
        const payload = new Uint8Array(uploadBytes);
        crypto.getRandomValues(payload);
        const form = new FormData();
        form.append('_token', csrf);
        form.append('data', new Blob([payload], { type: 'application/octet-stream' }), 'speedtest.bin');

        const t0 = performance.now();
        const res = await fetchOk(upUrl, {
            method: 'POST',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: form,
        });
        const data = await res.json();
        const received = data.bytes_received || payload.length;
        const sec = Math.max((performance.now() - t0) / 1000, 0.001);

        return (received * 8) / sec / 1_000_000;
    }

    async function runTest() {
        if (!els.run || !pingUrl || !downUrl || !upUrl) {
            return;
        }

        els.run.disabled = true;
        if (els.ring) {
            els.ring.classList.add('is-running');
        }
        resetStages();
        ['ping', 'down', 'up'].forEach((key) => {
            if (els[key]) {
                els[key].textContent = '…';
            }
        });

        let step = 'Ping';

        try {
            setStage('ping', 'active');
            if (els.status) {
                els.status.textContent = 'Testing ping…';
            }
            setRing('Ping');
            const ping = await measurePing();
            if (els.ping) {
                els.ping.textContent = ping.toFixed(0);
            }
            setStage('ping', 'done');

            step = 'Download';
            setStage('down', 'active');
            if (els.status) {
                els.status.textContent = 'Testing download…';
            }
            setRing('Down');
            const down = await measureDownload();
            if (els.down) {
                els.down.textContent = down.toFixed(2);
            }
            setStage('down', 'done');

            step = 'Upload';
            setStage('up', 'active');
            if (els.status) {
                els.status.textContent = 'Testing upload…';
            }
            setRing('Up');
            const up = await measureUpload();
            if (els.up) {
                els.up.textContent = up.toFixed(2);
            }
            setStage('up', 'done');
            setRing('Done');
            if (els.status) {
                els.status.textContent = 'Done · ' + new Date().toLocaleTimeString();
            }
        } catch (e) {
            failStatus(step, e.status || 0);
        }

        if (els.ring) {
            els.ring.classList.remove('is-running');
        }
        els.run.disabled = false;
    }

    els.run?.addEventListener('click', runTest);
})();
