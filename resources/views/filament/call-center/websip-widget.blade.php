@php
    $config = app(\App\Services\CallCenter\WebSipConfigPresenter::class)->forUser(auth()->user());
    $showDock = \App\Support\WebSipFeature::showsLiveCallUi(auth()->user());
    $settingsUrl = \App\Filament\Pages\ManageCallCenterSettings::getUrl();
    $fabCss = @filemtime(public_path('css/isp-live-call-fab.css')) ?: 1;
    $configured = (bool) ($config['configured'] ?? false);
@endphp

@if ($showDock)
    <link rel="stylesheet" href="{{ asset('css/isp-live-call-fab.css') }}?v={{ $fabCss }}" data-isp-live-call="1">

    @if ($config)
        <script data-cfasync="false">
            window.__ispWebSip = @json($config);
        </script>
        @if ($configured)
            <script src="{{ asset('vendor/jssip/jssip.min.js') }}?v=3.10.10" data-cfasync="false" data-isp-jssip-lib></script>
        @endif
        <script src="{{ asset('js/isp-websip.js') }}?v={{ @filemtime(public_path('js/isp-websip.js')) ?: 1 }}" data-cfasync="false"></script>
    @endif

    <div class="isp-live-call-dock" data-isp-live-call-dock>
        <div class="isp-websip-backdrop" data-isp-websip-backdrop aria-hidden="true"></div>

        <aside class="isp-websip-panel" data-isp-websip-panel aria-hidden="true" aria-label="WebSIP dialer">
            <div class="isp-websip-panel__head">
                <div>
                    <p class="isp-websip-panel__mode-label">PORT SIP PROFILE</p>
                    <p class="isp-websip-panel__mode-value">UDP 5060 → WSS</p>
                    <p class="isp-websip-panel__mode-sub" data-isp-websip-mode-hint>Same login as PortSIP · calls save to Call logs</p>
                </div>
                <button type="button" class="isp-websip-panel__close" data-isp-websip-close aria-label="Close dialer">×</button>
            </div>

            <div class="isp-websip-panel__status-wrap">
                <span class="isp-websip-panel__status" data-isp-websip-status>
                    {{ $configured ? 'Connecting…' : 'Setup required' }}
                </span>
            </div>

            <div class="isp-websip-panel__display" data-isp-websip-display>—</div>
            <input type="hidden" data-isp-websip-input value="" />

            <div class="isp-websip-keypad" data-isp-websip-keypad>
                @foreach (['1', '2', '3', '4', '5', '6', '7', '8', '9', '*', '0', '#'] as $key)
                    <button type="button" class="isp-websip-key" data-isp-websip-key="{{ $key }}">{{ $key }}</button>
                @endforeach
            </div>

            <p class="isp-websip-panel__actions">
                <button type="button" class="isp-websip-retry" data-isp-websip-retry hidden>
                    Retry WSS
                </button>
            </p>

            <div class="isp-websip-panel__toolbar">
                <button type="button" class="isp-websip-tool" data-isp-websip-backspace title="Backspace" aria-label="Backspace">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M21.5 5a1 1 0 0 0-1-.8l-9-2a1 1 0 0 0-.8.2l-11 10a1 1 0 0 0 0 1.4l11 10a1 1 0 0 0 1.4 0l9-10a1 1 0 0 0 .4-.8V5zM18.4 12.5l-8.1 9-7.5-6.8 7.5-6.8 8.1 9-1.4 1.2-6.7-7.5 6.7-7.5 1.4 1.3z"/></svg>
                </button>
                <button type="button" class="isp-websip-call-btn" data-isp-websip-dial-btn title="Call" aria-label="Call">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1.5 4.5a3 3 0 0 1 3-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 0 1-.694 1.955l-1.293.97c-.135.101-.164.28-.075.417l1.125 1.688a12.034 12.034 0 0 0 5.49 5.49l1.688-1.125a.375.375 0 0 1 .417-.075l.97 1.293a1.875 1.875 0 0 0 1.955.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 0 1-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5z" clip-rule="evenodd"/></svg>
                </button>
                <a href="{{ $settingsUrl }}" class="isp-websip-tool" title="SIP settings" aria-label="SIP settings">
                    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M11.078 2.25c-.917 0-1.699.663-1.85 1.567L9.05 4.89c-.02.12-.115.26-.297.348a7.493 7.493 0 0 0-.986.57c-.166.115-.334.126-.45.083L6.3 5.508a1.875 1.875 0 0 0-2.282.819l-.922 1.597a1.875 1.875 0 0 0 .432 2.385l.84.692c.095.078.17.229.154.43a7.598 7.598 0 0 0 0 1.139c.015.2-.059.352-.154.43l-.84.692a1.875 1.875 0 0 0-.432 2.385l.922 1.597a1.875 1.875 0 0 0 2.282.818l1.019-.382c.115-.043.283-.031.45.082.312.214.641.405.986.57.182.088.277.228.297.35l.178 1.071c.151.904.933 1.567 1.85 1.567h1.844c.916 0 1.699-.663 1.85-1.567l.178-1.072c.02-.12.114-.26.297-.349.345-.165.674-.356.986-.57.167-.114.335-.125.45-.082l1.02.382a1.875 1.875 0 0 0 2.28-.819l.923-1.597a1.875 1.875 0 0 0-.432-2.385l-.84-.692c-.095-.078-.17-.229-.154-.43a7.614 7.614 0 0 0 0-1.139c-.016-.2.059-.352.154-.43l.84-.692c.708-.582.891-1.59.433-2.385l-.922-1.597a1.875 1.875 0 0 0-2.282-.818l-1.02.382c-.114.043-.282.031-.449-.083a7.507 7.507 0 0 0-.985-.57c-.183-.087-.277-.226-.297-.348l-.179-1.072a1.875 1.875 0 0 0-1.85-1.567h-1.843zM12 15.75a3.75 3.75 0 1 0 0-7.5 3.75 3.75 0 0 0 0 7.5z" clip-rule="evenodd"/></svg>
                </a>
            </div>

            <label class="isp-websip-volume">
                <span>Volume</span>
                <input type="range" min="0" max="100" value="80" data-isp-websip-volume />
            </label>

            @unless ($configured)
                <p class="isp-websip-panel__hint">
                    <a href="{{ $settingsUrl }}">SIP settings</a> — WebSIP password ও SIP domain দিন (PortSIP-এর মতো)।
                </p>
            @endunless
        </aside>

        <button
            type="button"
            class="isp-live-call-fab"
            data-isp-websip-fab
            title="লাইভ কল — WebSIP"
            aria-label="লাইভ কল"
        >
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M1.5 4.5a3 3 0 013-3h1.372c.86 0 1.61.586 1.819 1.42l1.105 4.423a1.875 1.875 0 01-.694 1.955l-1.293.97c-.135.101-.164.28-.075.417l1.125 1.688a12.034 12.034 0 005.49 5.49l1.688-1.125a.375.375 0 01.417-.075l.97 1.293a1.875 1.875 0 001.955.694l4.423 1.105c.834.209 1.42.959 1.42 1.82V19.5a3 3 0 01-3 3h-2.25C8.552 22.5 1.5 15.448 1.5 6.75V4.5z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>

@endif
