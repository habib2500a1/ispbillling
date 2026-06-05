@php
    $speedtest = $speedtest ?? [
        'ping_url' => (string) config('portal.speed_test.external.ping_url'),
        'download_url' => (string) config('portal.speed_test.external.download_url'),
        'upload_url' => (string) config('portal.speed_test.external.upload_url'),
    ];
@endphp
{{-- Native speed test (START) — external CORS endpoints via portal-speedtest-live.js --}}
<section
    id="isp-st"
    class="portal-st"
    data-ping-url="{{ $speedtest['ping_url'] }}"
    data-download-url="{{ $speedtest['download_url'] }}"
    data-upload-url="{{ $speedtest['upload_url'] }}">
    <div class="portal-st__stage">
        <div class="portal-st__gauge" id="isp-st-gauge" role="img" aria-label="Speed gauge">
            <span class="portal-st__gauge-phase" id="isp-st-phase">READY</span>
            <span class="portal-st__gauge-value" id="isp-st-value">0</span>
            <span class="portal-st__gauge-unit">Mbps</span>
        </div>
        <button type="button" id="isp-st-start" class="portal-st__start">START</button>
    </div>

    <div class="portal-st__tiles">
        <div class="portal-st__tile portal-st__tile--down">
            <span class="portal-st__tile-label">↓ Download</span>
            <span class="portal-st__tile-value"><span id="isp-st-down">—</span> <small>Mbps</small></span>
        </div>
        <div class="portal-st__tile portal-st__tile--up">
            <span class="portal-st__tile-label">↑ Upload</span>
            <span class="portal-st__tile-value"><span id="isp-st-up">—</span> <small>Mbps</small></span>
        </div>
        <div class="portal-st__tile portal-st__tile--ping">
            <span class="portal-st__tile-label">◎ Latency</span>
            <span class="portal-st__tile-value"><span id="isp-st-ping">—</span> <small>ms</small></span>
        </div>
    </div>

    <p class="portal-st__status" id="isp-st-status">Ready to start.</p>
</section>
