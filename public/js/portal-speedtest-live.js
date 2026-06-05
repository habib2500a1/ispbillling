/**
 * Portal speed test — native UI (our START button), measured against an external
 * CORS-enabled backend (speedtest.sg by default). No iframe, no external site chrome.
 *
 * Endpoints (configurable via config/portal.php → speed_test.external):
 *   ping.php           GET  → tiny body, used for latency
 *   download.php?bytes=N GET → N bytes of garbage, used for download throughput
 *   upload.php         POST → discards body, used for upload throughput
 */
(function () {
    const panel = document.getElementById('isp-st');
    if (!panel) {
        return;
    }

    const pingUrl = panel.dataset.pingUrl;
    const downloadUrl = panel.dataset.downloadUrl;
    const uploadUrl = panel.dataset.uploadUrl;

    const startBtn = document.getElementById('isp-st-start');
    const gauge = document.getElementById('isp-st-gauge');
    const phaseEl = document.getElementById('isp-st-phase');
    const valueEl = document.getElementById('isp-st-value');
    const downEl = document.getElementById('isp-st-down');
    const upEl = document.getElementById('isp-st-up');
    const pingEl = document.getElementById('isp-st-ping');
    const statusEl = document.getElementById('isp-st-status');

    // Tunables
    const PING_SAMPLES = 6;
    const DL_DURATION_MS = 9000;
    const DL_STREAMS = 6;
    const DL_BYTES_PER_REQ = 50 * 1000 * 1000; // server honours arbitrary sizes; aborted early
    const UL_DURATION_MS = 8000;
    const UL_STREAMS = 3;
    const UL_BLOB_BYTES = 1 * 1000 * 1000;

    let running = false;

    function cacheBust(url) {
        return url + (url.indexOf('?') >= 0 ? '&' : '?') + '_=' + Date.now() + Math.random().toString(36).slice(2);
    }

    function setPhase(text, cls) {
        if (phaseEl) {
            phaseEl.textContent = text;
        }
        if (gauge) {
            gauge.classList.remove('is-ping', 'is-down', 'is-up', 'is-done');
            if (cls) {
                gauge.classList.add(cls);
            }
        }
    }

    function setGauge(mbps) {
        if (!valueEl) {
            return;
        }
        const v = mbps >= 100 ? mbps.toFixed(0) : mbps.toFixed(1);
        valueEl.textContent = v;
    }

    function setStatus(text) {
        if (statusEl) {
            statusEl.textContent = text;
        }
    }

    async function measurePing() {
        setPhase('PING', 'is-ping');
        setStatus('Measuring latency…');
        let best = Infinity;
        // warm-up (DNS/TLS) — not counted
        try {
            await fetch(cacheBust(pingUrl), { cache: 'no-store', mode: 'cors' });
        } catch (e) { /* ignore */ }

        for (let i = 0; i < PING_SAMPLES; i++) {
            const t0 = performance.now();
            try {
                await fetch(cacheBust(pingUrl), { cache: 'no-store', mode: 'cors' });
            } catch (e) {
                continue;
            }
            const dt = performance.now() - t0;
            if (dt < best) {
                best = dt;
            }
            if (pingEl && Number.isFinite(best)) {
                pingEl.textContent = best.toFixed(0);
            }
        }
        return Number.isFinite(best) ? best : null;
    }

    async function downloadStream(abort, onBytes) {
        while (!abort.signal.aborted) {
            try {
                const url = cacheBust(downloadUrl + (downloadUrl.indexOf('?') >= 0 ? '&' : '?') + 'bytes=' + DL_BYTES_PER_REQ);
                const res = await fetch(url, { cache: 'no-store', mode: 'cors', signal: abort.signal });
                if (!res.body) {
                    // No streaming support — fall back to full blob
                    const buf = await res.arrayBuffer();
                    onBytes(buf.byteLength);
                    continue;
                }
                const reader = res.body.getReader();
                while (true) {
                    const { done, value } = await reader.read();
                    if (done) {
                        break;
                    }
                    onBytes(value.length);
                }
            } catch (e) {
                if (abort.signal.aborted) {
                    return;
                }
                // brief backoff before retry
                await new Promise((r) => setTimeout(r, 150));
            }
        }
    }

    async function measureDownload() {
        setPhase('DOWNLOAD', 'is-down');
        setStatus('Testing download…');
        const abort = new AbortController();
        let bytes = 0;
        const t0 = performance.now();

        const onBytes = (n) => {
            bytes += n;
            const sec = (performance.now() - t0) / 1000;
            if (sec > 0.25) {
                setGauge((bytes * 8) / sec / 1e6);
            }
        };

        const streams = [];
        for (let i = 0; i < DL_STREAMS; i++) {
            streams.push(downloadStream(abort, onBytes));
        }

        await new Promise((r) => setTimeout(r, DL_DURATION_MS));
        abort.abort();
        try {
            await Promise.allSettled(streams);
        } catch (e) { /* ignore */ }

        const sec = Math.max((performance.now() - t0) / 1000, 0.001);
        const mbps = (bytes * 8) / sec / 1e6;
        setGauge(mbps);
        if (downEl) {
            downEl.textContent = mbps >= 100 ? mbps.toFixed(0) : mbps.toFixed(1);
        }
        return mbps;
    }

    function buildUploadBlob() {
        const arr = new Uint8Array(UL_BLOB_BYTES);
        // pseudo-random so it is not trivially compressible
        for (let i = 0; i < arr.length; i += 4096) {
            arr[i] = (Math.random() * 256) | 0;
        }
        return new Blob([arr], { type: 'application/octet-stream' });
    }

    function uploadOnce(blob, abort, onBytes) {
        return new Promise((resolve) => {
            let last = 0;
            const xhr = new XMLHttpRequest();
            xhr.open('POST', cacheBust(uploadUrl), true);
            xhr.upload.onprogress = (e) => {
                if (e.loaded > last) {
                    onBytes(e.loaded - last);
                    last = e.loaded;
                }
            };
            xhr.onloadend = () => resolve();
            xhr.onerror = () => resolve();
            abort.signal.addEventListener('abort', () => {
                try { xhr.abort(); } catch (e) { /* ignore */ }
                resolve();
            });
            try {
                xhr.send(blob);
            } catch (e) {
                resolve();
            }
        });
    }

    async function uploadStream(blob, abort, onBytes) {
        while (!abort.signal.aborted) {
            await uploadOnce(blob, abort, onBytes);
        }
    }

    async function measureUpload() {
        setPhase('UPLOAD', 'is-up');
        setStatus('Testing upload…');
        const abort = new AbortController();
        const blob = buildUploadBlob();
        let bytes = 0;
        const t0 = performance.now();

        const onBytes = (n) => {
            bytes += n;
            const sec = (performance.now() - t0) / 1000;
            if (sec > 0.25) {
                setGauge((bytes * 8) / sec / 1e6);
            }
        };

        const streams = [];
        for (let i = 0; i < UL_STREAMS; i++) {
            streams.push(uploadStream(blob, abort, onBytes));
        }

        await new Promise((r) => setTimeout(r, UL_DURATION_MS));
        abort.abort();
        try {
            await Promise.allSettled(streams);
        } catch (e) { /* ignore */ }

        const sec = Math.max((performance.now() - t0) / 1000, 0.001);
        const mbps = (bytes * 8) / sec / 1e6;
        setGauge(mbps);
        if (upEl) {
            upEl.textContent = mbps >= 100 ? mbps.toFixed(0) : mbps.toFixed(1);
        }
        return mbps;
    }

    async function run() {
        if (running) {
            return;
        }
        if (!pingUrl || !downloadUrl || !uploadUrl) {
            setStatus('Speed test server not configured.');
            return;
        }
        running = true;
        startBtn.disabled = true;
        startBtn.classList.add('is-running');
        startBtn.textContent = 'TESTING…';
        if (downEl) downEl.textContent = '—';
        if (upEl) upEl.textContent = '—';
        if (pingEl) pingEl.textContent = '—';
        setGauge(0);

        try {
            await measurePing();
            setGauge(0);
            await measureDownload();
            setGauge(0);
            await measureUpload();
            setPhase('DONE', 'is-done');
            setStatus('Test complete. Tap START to run again.');
        } catch (e) {
            setPhase('ERROR', null);
            setStatus('Could not reach the test server. Check your connection and retry.');
        } finally {
            running = false;
            startBtn.disabled = false;
            startBtn.classList.remove('is-running');
            startBtn.textContent = 'START';
        }
    }

    startBtn.addEventListener('click', run);
})();
