@php
    $stats = $this->getMonitoringStats();
    $sync = $this->getSyncStatus();
    $pollSeconds = (int) config('bandwidth.live_page_poll_seconds', 60);
    $liveCheck = (bool) config('bandwidth.live_online_check', false);
    $livePollSeconds = $liveCheck ? max(5, (int) config('bandwidth.live_online_cache_seconds', 5)) : 0;
    $syncAt = ! empty($sync['updated_at'])
        ? rescue(fn () => \Carbon\Carbon::parse($sync['updated_at'])->format('d M Y h:i A'), $sync['updated_at'])
        : null;
@endphp

<x-filament-panels::page class="isp-online-clients-page">
    <div
        class="space-y-4"
        @if ($pollSeconds > 0)
            wire:poll.{{ $pollSeconds }}s="refreshLiveData"
        @endif
        @if ($livePollSeconds > 0)
            wire:poll.{{ $livePollSeconds }}s="$refresh"
        @endif
    >
        <section class="isp-online-clients-hero">
            <div>
                <p class="isp-online-clients-hero__eyebrow">Network operations</p>
                <h2 class="isp-online-clients-hero__title">Live PPP / online clients</h2>
                <p class="isp-online-clients-hero__sub">
                    Real-time sessions from MikroTik — login, logout, client IP, router NAS, MAC, and traffic.
                </p>
            </div>
            @if ($syncAt)
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

        <div class="isp-online-clients-stats">
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

        @if ($pollSeconds > 0 || $livePollSeconds > 0)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                @if ($pollSeconds > 0)
                    Session sync every {{ $pollSeconds }}s.
                @endif
                @if ($livePollSeconds > 0)
                    Live RouterOS check every {{ $livePollSeconds }}s (set <code>BANDWIDTH_LIVE_ONLINE_CHECK=true</code>).
                @endif
                Use filter <strong>Online only</strong> to hide offline users.
            </p>
        @endif

        <div class="isp-online-clients-table-wrap">
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
