@php
    $dash = $dashboard;
    $summary = $dash['summary'] ?? [];
    $alerts = $dash['alerts'] ?? [];
    $chips = $dash['chips'] ?? [];
@endphp

<link rel="stylesheet" href="{{ asset('css/ai-copilot-pro.css') }}?v={{ @filemtime(public_path('css/ai-copilot-pro.css')) ?: 1 }}">
<link rel="manifest" href="{{ asset('manifest-ai.json') }}">
<script src="{{ asset('js/ai-copilot.js') }}?v={{ @filemtime(public_path('js/ai-copilot.js')) ?: 1 }}" defer data-cfasync="false"></script>

<x-filament-panels::page class="ai-copilot-page">
    <div
        class="ai-shell"
        data-ai-copilot
        data-ask-url="{{ route('admin.ai-copilot.ask') }}"
        data-dashboard-url="{{ route('admin.ai-copilot.dashboard') }}"
    >
        <header class="ai-topbar ai-glass">
            <div>
                <p class="ai-eyebrow">ISP intelligence layer</p>
                <h1 class="ai-topbar__title">AI Operations Copilot</h1>
            </div>
            <div class="ai-topbar__actions">
                <button type="button" class="ai-icon-btn" wire:click="toggleAlerts" aria-label="Alerts">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="h-5 w-5" />
                    <span class="ai-badge" data-ai-alert-count @if (count($alerts) === 0) hidden @endif>{{ count($alerts) }}</span>
                </button>
                <button type="button" class="ai-icon-btn" data-ai-theme-toggle aria-label="Theme">
                    <x-filament::icon icon="heroicon-o-moon" class="h-5 w-5 ai-theme-icon--dark" />
                    <x-filament::icon icon="heroicon-o-sun" class="h-5 w-5 ai-theme-icon--light" />
                </button>
            </div>
        </header>

        @if ($showAlerts)
            <aside class="ai-alerts-drawer ai-glass">
                <div class="ai-alerts-drawer__head">
                    <h3>AI Alert Center</h3>
                    <button type="button" wire:click="toggleAlerts" class="ai-icon-btn">
                        <x-filament::icon icon="heroicon-o-x-mark" class="h-4 w-4" />
                    </button>
                </div>
                <div class="ai-alerts-list" data-ai-alerts-list>
                    @forelse ($alerts as $alert)
                        <article @class(['ai-alert-row', 'ai-alert-row--' . ($alert['severity'] ?? 'medium')])>
                            <span class="ai-alert-row__domain">{{ $alert['domain'] ?? 'ops' }}</span>
                            <strong>{{ $alert['title'] ?? '' }}</strong>
                            <span class="ai-alert-row__hint">{{ $alert['hint'] ?? '' }}</span>
                            @if (! empty($alert['url']))
                                <a href="{{ $alert['url'] }}" class="ai-alert-row__link">Open →</a>
                            @endif
                        </article>
                    @empty
                        <p class="ai-empty">No active alerts.</p>
                    @endforelse
                </div>
            </aside>
        @endif

        <div class="ai-layout">
            <section class="ai-sidebar ai-glass" data-ai-sidebar>
                <h3 class="ai-sidebar__title">Executive summary</h3>
                <div class="ai-kpi-grid">
                    <article class="ai-kpi ai-kpi--emerald">
                        <span>Collected today</span>
                        <strong data-ai-kpi="collected_today">{{ number_format((float) ($summary['collected_today'] ?? 0), 0) }} BDT</strong>
                    </article>
                    <article class="ai-kpi ai-kpi--cyan">
                        <span>Open tickets</span>
                        <strong data-ai-kpi="open_tickets">{{ (int) ($summary['open_tickets'] ?? 0) }}</strong>
                    </article>
                    <article class="ai-kpi ai-kpi--amber">
                        <span>Offline subs</span>
                        <strong data-ai-kpi="customers_offline">{{ (int) ($summary['customers_offline'] ?? 0) }}</strong>
                    </article>
                    <article class="ai-kpi ai-kpi--rose">
                        <span>Active faults</span>
                        <strong data-ai-kpi="active_faults">{{ (int) ($summary['active_faults'] ?? 0) }}</strong>
                    </article>
                </div>
                <p class="ai-health">Network health <strong data-ai-kpi="network_health">{{ (int) ($summary['network_health'] ?? 0) }}/100</strong></p>

                <h3 class="ai-sidebar__title">Quick asks</h3>
                <div class="ai-chips" data-ai-chips>
                    @foreach ($chips as $chip)
                        <button type="button" class="ai-chip" data-ai-chip="{{ $chip }}">{{ $chip }}</button>
                    @endforeach
                </div>

                <h3 class="ai-sidebar__title">Domains</h3>
                <div class="ai-domain-grid">
                    <span class="ai-domain ai-domain--billing">Billing</span>
                    <span class="ai-domain ai-domain--noc">NOC</span>
                    <span class="ai-domain ai-domain--support">Support</span>
                    <span class="ai-domain ai-domain--gis">GIS</span>
                    <span class="ai-domain ai-domain--inventory">Inventory</span>
                    <span class="ai-domain ai-domain--hr">HR</span>
                </div>
            </section>

            <section class="ai-chat ai-glass" wire:ignore.self>
                <div class="ai-chat__head">
                    <span>Conversational command center</span>
                    <button type="button" class="ai-text-btn" data-ai-clear>Clear</button>
                </div>

                <div class="ai-messages" data-ai-messages></div>

                <form class="ai-composer" data-ai-composer autocomplete="off">
                    <button type="button" class="ai-voice-btn" data-ai-voice title="Voice input" aria-label="Voice input">
                        <x-filament::icon icon="heroicon-o-microphone" class="h-5 w-5" />
                    </button>
                    <input
                        type="text"
                        class="ai-composer__input"
                        placeholder="Ask anything — e.g. Show offline ONUs in Area A…"
                        autocomplete="off"
                        data-ai-input
                    >
                    <button type="submit" class="ai-send-btn" data-ai-send aria-label="Send">
                        <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5" />
                    </button>
                </form>
                <p class="ai-disclaimer">Advisory only · No automatic configuration or data changes</p>
            </section>
        </div>
    </div>
</x-filament-panels::page>
