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
    <div class="ai-shell" data-ai-copilot wire:poll.90s="refreshDashboard">
        <header class="ai-topbar ai-glass">
            <div>
                <p class="ai-eyebrow">ISP intelligence layer</p>
                <h1 class="ai-topbar__title">AI Operations Copilot</h1>
            </div>
            <div class="ai-topbar__actions">
                <button type="button" class="ai-icon-btn" wire:click="toggleAlerts" aria-label="Alerts">
                    <x-filament::icon icon="heroicon-o-bell-alert" class="h-5 w-5" />
                    @if (count($alerts) > 0)
                        <span class="ai-badge">{{ count($alerts) }}</span>
                    @endif
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
                <div class="ai-alerts-list">
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
            <section class="ai-sidebar ai-glass">
                <h3 class="ai-sidebar__title">Executive summary</h3>
                <div class="ai-kpi-grid">
                    <article class="ai-kpi ai-kpi--emerald">
                        <span>Collected today</span>
                        <strong>{{ number_format((float) ($summary['collected_today'] ?? 0), 0) }} BDT</strong>
                    </article>
                    <article class="ai-kpi ai-kpi--cyan">
                        <span>Open tickets</span>
                        <strong>{{ (int) ($summary['open_tickets'] ?? 0) }}</strong>
                    </article>
                    <article class="ai-kpi ai-kpi--amber">
                        <span>Offline subs</span>
                        <strong>{{ (int) ($summary['customers_offline'] ?? 0) }}</strong>
                    </article>
                    <article class="ai-kpi ai-kpi--rose">
                        <span>Active faults</span>
                        <strong>{{ (int) ($summary['active_faults'] ?? 0) }}</strong>
                    </article>
                </div>
                <p class="ai-health">Network health <strong>{{ (int) ($summary['network_health'] ?? 0) }}/100</strong></p>

                <h3 class="ai-sidebar__title">Quick asks</h3>
                <div class="ai-chips">
                    @foreach ($chips as $chip)
                        <button type="button" class="ai-chip" wire:click="askChip(@js($chip))">{{ $chip }}</button>
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

            <section class="ai-chat ai-glass">
                <div class="ai-chat__head">
                    <span>Conversational command center</span>
                    <button type="button" class="ai-text-btn" wire:click="clearChat">Clear</button>
                </div>

                <div class="ai-messages" data-ai-messages>
                    @foreach ($messages as $msg)
                        <article @class(['ai-msg', 'ai-msg--' . ($msg['role'] ?? 'assistant')])>
                            <div class="ai-msg__bubble">
                                <p>{{ $msg['text'] ?? '' }}</p>
                                @if (! empty($msg['cards']))
                                    <div class="ai-insight-cards">
                                        @foreach ($msg['cards'] as $card)
                                            <div @class(['ai-insight-card', 'ai-insight-card--' . ($card['tone'] ?? 'indigo')])>
                                                <span class="ai-insight-card__title">{{ $card['title'] ?? '' }}</span>
                                                <strong>{{ $card['value'] ?? '' }}</strong>
                                                @if (! empty($card['hint']))
                                                    <span class="ai-insight-card__hint">{{ $card['hint'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                                @if (! empty($msg['table']['rows']))
                                    <div class="ai-table-wrap">
                                        <table class="ai-table">
                                            <thead>
                                                <tr>
                                                    @foreach ($msg['table']['headers'] ?? [] as $h)
                                                        <th>{{ $h }}</th>
                                                    @endforeach
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($msg['table']['rows'] as $row)
                                                    <tr>
                                                        @foreach ($row as $cell)
                                                            <td>{{ $cell }}</td>
                                                        @endforeach
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                                @if (! empty($msg['links']))
                                    <div class="ai-msg__links">
                                        @foreach ($msg['links'] as $link)
                                            <a href="{{ $link['url'] }}" class="ai-link-btn">{{ $link['label'] }}</a>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                    <div wire:loading wire:target="sendQuery,askChip" class="ai-typing">
                        <span></span><span></span><span></span>
                    </div>
                </div>

                <form class="ai-composer" wire:submit.prevent="sendQuery">
                    <button type="button" class="ai-voice-btn" data-ai-voice title="Voice (coming soon)" aria-label="Voice input">
                        <x-filament::icon icon="heroicon-o-microphone" class="h-5 w-5" />
                    </button>
                    <input
                        type="text"
                        class="ai-composer__input"
                        placeholder="Ask anything — e.g. Show offline ONUs in Area A…"
                        wire:model="userQuery"
                        autocomplete="off"
                        data-ai-input
                    >
                    <button type="submit" class="ai-send-btn" wire:loading.attr="disabled">
                        <x-filament::icon icon="heroicon-o-paper-airplane" class="h-5 w-5" />
                    </button>
                </form>
                <p class="ai-disclaimer">Advisory only · No automatic configuration or data changes</p>
            </section>
        </div>
    </div>
</x-filament-panels::page>
