@php
    use App\Filament\Pages\OnlineClientsMonitoring;

    $stats = $this->getMonitoringStats();
    $sync = $this->getSyncStatus();
    $pollSeconds = (int) config('bandwidth.live_page_poll_seconds', 60);
    $activeTableSearch = $this->getActiveTableSearch();
    $searchJsVersion = @filemtime(public_path('js/online-clients-search.js')) ?: 1;
    $liveCheck = (bool) config('bandwidth.live_online_check', false);
    $livePollSeconds = $liveCheck ? max(5, (int) config('bandwidth.live_online_cache_seconds', 5)) : 0;
    $syncAt = ! empty($sync['updated_at'])
        ? rescue(fn () => \Carbon\Carbon::parse($sync['updated_at'])->format('d M Y h:i A'), $sync['updated_at'])
        : null;
    $syncRunning = \App\Services\Bandwidth\BandwidthSyncStatus::isRunning(
        \App\Support\TenantResolver::requiredTenantId()
    );
@endphp

{!! \App\Support\NetworkStyles::navigatedScript() !!}
<script src="{{ asset('js/online-clients-search.js') }}?v={{ $searchJsVersion }}" defer></script>

<x-filament-panels::page class="isp-online-clients-page oc-pro">
    <div class="net-noc-pro oc-pro-layout space-y-4">
        <section class="net-mon-hero">
            <div>
                <p style="margin:0;font-size:0.68rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;opacity:0.9;">Network operations</p>
                <h2 class="net-mon-hero__title">Live PPP / online clients</h2>
                <p class="net-mon-hero__sub">
                    Real-time sessions from MikroTik — login, logout, client IP, router NAS, MAC, and traffic.
                </p>
            </div>
            @if ($syncRunning)
                <div class="isp-online-clients-hero__sync">
                    <span class="isp-live-dot isp-live-dot--pulse" aria-hidden="true"></span>
                    <div>
                        <strong>Background sync</strong>
                        <span>Running on server — page stays fast</span>
                    </div>
                </div>
            @elseif ($syncAt)
                <div class="isp-online-clients-hero__sync">
                    <span class="isp-live-dot" aria-hidden="true"></span>
                    <div>
                        <strong>Last sync</strong>
                        <span>{{ $syncAt }}</span>
                        <span class="block text-xs opacity-80">
                            Router: {{ number_format((int) ($sync['api']['sessions'] ?? 0)) }} sessions
                            @if (! empty($sync['matched_subscribers']))
                                · Matched {{ number_format((int) $sync['matched_subscribers']) }}
                            @endif
                        </span>
                    </div>
                </div>
            @endif
        </section>

        <div
            class="isp-online-clients-stats oc-pro-stats"
            wire:key="online-stats-{{ $stats['online'] }}-{{ $stats['active_sessions'] }}"
            @if ($pollSeconds > 0 && blank($activeTableSearch))
                wire:poll.{{ $pollSeconds }}s="refreshLiveData"
            @endif
        >
            <div class="isp-online-clients-stat isp-online-clients-stat--blue">
                <span class="isp-online-clients-stat__label">PPP subscribers</span>
                <strong>{{ number_format($stats['total']) }}</strong>
            </div>
            <div class="isp-online-clients-stat isp-online-clients-stat--teal">
                <span class="isp-online-clients-stat__label">Online now</span>
                <strong>{{ number_format($stats['online']) }}</strong>
            </div>
            <div class="isp-online-clients-stat isp-online-clients-stat--slate">
                <span class="isp-online-clients-stat__label">Offline</span>
                <strong>{{ number_format($stats['offline']) }}</strong>
            </div>
            <div class="isp-online-clients-stat isp-online-clients-stat--violet">
                <span class="isp-online-clients-stat__label">DB active sessions</span>
                <strong>{{ number_format($stats['active_sessions']) }}</strong>
            </div>
        </div>

        @if (! $this->mikrotikRoutingHealthy())
            <div class="isp-online-clients-alert" role="alert">
                <strong>MikroTik server disabled or missing.</strong>
                Live online status cannot update — enable your router under
                <strong>Network → MikroTik servers</strong>, then click
                <strong>Sync live sessions</strong>.
            </div>
        @endif

        @if ($stats['sync_stale'] ?? false)
            <div class="isp-online-clients-alert" role="status">
                MikroTik sync is stale — counts use last known online data.
                Click <strong>Sync live sessions</strong> to refresh from the router.
            </div>
        @endif

        @if ($stats['unmatched_hint'])
            <div class="isp-online-clients-alert" role="status">
                Router reports active sessions but no subscriber is marked online.
                Click <strong>Sync live sessions</strong> or check PPP usernames match MikroTik secrets.
            </div>
        @endif

        @if ($pollSeconds > 0 && blank($activeTableSearch))
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Session sync every {{ $pollSeconds }}s.
                Use filter <strong>Online only</strong> to hide offline users.
            </p>
        @endif

        <form
            method="GET"
            action="{{ OnlineClientsMonitoring::getUrl() }}"
            class="oc-pro-search-toolbar"
            id="oc-clients-search-form"
            data-navigate="false"
        >
            <label class="sr-only" for="oc-clients-search-input">Search online clients</label>
            <div class="oc-pro-search-field">
                <svg class="oc-pro-search-field__icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M9 3.5a5.5 5.5 0 1 0 0 11 5.5 5.5 0 0 0 0-11ZM2 9a7 7 0 1 1 12.452 4.391l3.328 3.329a.75.75 0 1 1-1.06 1.06l-3.329-3.328A7 7 0 0 1 2 9Z" clip-rule="evenodd" />
                </svg>
                <input
                    id="oc-clients-search-input"
                    type="search"
                    name="tableSearch"
                    value="{{ $activeTableSearch }}"
                    placeholder="Search code, name, phone, PPP user, IP…"
                    maxlength="1000"
                    autocomplete="off"
                    oninput="window.clearTimeout(window._ocSearchTimer); window._ocSearchTimer = window.setTimeout(function () { window.ispSubmitOnlineClientsSearch && window.ispSubmitOnlineClientsSearch(); }, 500);"
                >
            </div>
            <button type="submit" class="oc-pro-search-submit">
                Search
            </button>
            @if (filled($activeTableSearch))
                <a href="{{ OnlineClientsMonitoring::getUrl() }}" class="oc-pro-search-clear" data-navigate="false">
                    Clear
                </a>
                <span class="oc-pro-search-active" role="status">
                    Filtered: “{{ $activeTableSearch }}”
                </span>
            @endif
        </form>

        <div
            class="isp-online-clients-table-wrap oc-pro-table"
            wire:key="oc-clients-table-{{ $this->tableResultsEpoch }}-{{ md5($activeTableSearch.json_encode($this->tableFilters ?? [])) }}"
        >
            {{ $this->table }}
        </div>

    </div>
</x-filament-panels::page>
