@php
    $d = $this->dashboard;
    $kpis = $d['kpis'] ?? [];
    $channels = $d['channels'] ?? [];
    $billing = $d['billing_automation'] ?? [];
    $analytics = $d['analytics'] ?? ['labels' => [], 'sent' => [], 'failed' => []];
    $maxBar = max(array_merge($analytics['sent'] ?? [1], [1]));

    $kpiCards = [
        ['key' => 'sms_today', 'label' => 'SMS today', 'class' => 'isp-comms-kpi--sms'],
        ['key' => 'whatsapp_today', 'label' => 'WhatsApp today', 'class' => 'isp-comms-kpi--wa'],
        ['key' => 'email_today', 'label' => 'Email today', 'class' => 'isp-comms-kpi--email'],
        ['key' => 'push_today', 'label' => 'Push today', 'class' => 'isp-comms-kpi--push'],
        ['key' => 'failed_24h', 'label' => 'Failed 24h', 'class' => 'isp-comms-kpi--fail'],
        ['key' => 'scheduled', 'label' => 'Scheduled', 'class' => 'isp-comms-kpi--sched'],
        ['key' => 'active_campaigns', 'label' => 'Active campaigns', 'class' => 'isp-comms-kpi--camp'],
        ['key' => 'delivery_rate', 'label' => 'Delivery rate %', 'class' => 'isp-comms-kpi--rate', 'suffix' => '%'],
    ];

    $quickActions = [
        ['url' => \App\Filament\Pages\SendSms::getUrl(), 'title' => 'Send SMS', 'desc' => 'Single subscriber'],
        ['url' => \App\Filament\Pages\BulkSmsCampaign::getUrl(), 'title' => 'Bulk campaign', 'desc' => 'Targeted blast'],
        ['url' => \App\Filament\Pages\BroadcastOutage::getUrl(), 'title' => 'Outage broadcast', 'desc' => 'Maintenance notice'],
        ['url' => \App\Filament\Resources\SmsTemplateResource::getUrl(), 'title' => 'Templates', 'desc' => 'Message library'],
        ['url' => \App\Filament\Pages\SmsGatewaySetup::getUrl(), 'title' => 'SMS gateway', 'desc' => 'Balance & test'],
        ['url' => \App\Filament\Pages\WhatsAppBotHub::getUrl(), 'title' => 'WhatsApp', 'desc' => 'Bot & Cloud API'],
        ['url' => \App\Filament\Pages\ManageNotifications::getUrl(), 'title' => 'All settings', 'desc' => 'Channels & events'],
        ['url' => \App\Filament\Pages\FiberPlantMap::getUrl(), 'title' => 'GIS targeting', 'desc' => 'Map → bulk audience'],
    ];
@endphp

<x-filament-panels::page class="isp-comms-page isp-hub-page">
    <link rel="stylesheet" href="{{ asset('css/comms-hub.css') }}?v={{ @filemtime(public_path('css/comms-hub.css')) ?: 1 }}">
    <script src="{{ asset('js/comms-hub.js') }}?v={{ @filemtime(public_path('js/comms-hub.js')) ?: 1 }}" defer data-cfasync="false"></script>

    <div class="space-y-5">
        {{-- Hero --}}
        <section class="isp-comms-hero isp-comms-glass">
            <p class="text-xs uppercase tracking-wider opacity-80 mb-1">ISP Communication Hub</p>
            <h1 class="isp-comms-hero__title">Communication Command Center</h1>
            <p class="isp-comms-hero__sub">
                SMS · WhatsApp · Email · Push — billing automation, outage broadcasts, ticket alerts, campaigns & delivery analytics.
            </p>
            <div class="isp-comms-search" wire:ignore.self>
                <input
                    type="search"
                    wire:model.live.debounce.300ms="searchQuery"
                    placeholder="Search customer, phone, SMS, template, campaign…"
                    autocomplete="off"
                    aria-label="Global communication search"
                >
                @if (strlen($this->searchQuery) >= 2)
                    <div class="isp-comms-search-results">
                        @forelse ($this->searchResults as $row)
                            <a href="{{ $row['url'] ?? '#' }}">
                                <strong>{{ $row['label'] }}</strong>
                                <span class="block opacity-70">{{ ucfirst($row['type']) }} · {{ $row['meta'] ?? '' }}</span>
                            </a>
                        @empty
                            <p class="p-3 text-sm opacity-70">No matches</p>
                        @endforelse
                    </div>
                @endif
            </div>
            <div class="flex flex-wrap gap-2 mt-3 relative z-10">
                @foreach ($channels as $ch)
                    <span class="isp-comms-channel-pill {{ ($ch['on'] ?? false) ? 'isp-comms-channel-pill--on' : 'isp-comms-channel-pill--off' }}">
                        {{ $ch['label'] }}{{ ($ch['on'] ?? false) ? ' ✓' : '' }}
                    </span>
                @endforeach
            </div>
        </section>

        {{-- KPI strip --}}
        <div class="isp-comms-kpi-grid">
            @foreach ($kpiCards as $card)
                <div class="isp-comms-kpi isp-comms-glass {{ $card['class'] }}">
                    <span>{{ $card['label'] }}</span>
                    <strong data-comms-kpi="{{ (int) ($kpis[$card['key']] ?? 0) }}">{{ number_format($kpis[$card['key']] ?? 0) }}{{ $card['suffix'] ?? '' }}</strong>
                </div>
            @endforeach
        </div>

        {{-- Tabs --}}
        <div class="isp-comms-tabs">
            @foreach (['dashboard' => 'Dashboard', 'campaigns' => 'Campaigns', 'templates' => 'Templates', 'analytics' => 'Analytics', 'outage' => 'Outage', 'billing' => 'Billing auto', 'inbox' => 'Inbox'] as $tab => $label)
                <button type="button" wire:click="setTab('{{ $tab }}')" class="isp-comms-tab {{ $this->activeTab === $tab ? 'isp-comms-tab--active' : '' }}">{{ $label }}</button>
            @endforeach
            <button type="button" wire:click="refreshDashboard" class="isp-comms-tab ml-auto" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="refreshDashboard">↻ Refresh</span>
                <span wire:loading wire:target="refreshDashboard">…</span>
            </button>
        </div>

        <div class="isp-comms-layout">
            {{-- Left nav --}}
            <nav class="isp-comms-layout__nav isp-comms-glass p-3">
                <p class="text-xs uppercase opacity-60 mb-2 px-1">Channels & tools</p>
                @foreach ($this->getNavLinks() as $link)
                    <a href="{{ $link['url'] }}">{{ $link['label'] }}</a>
                @endforeach
                <a href="{{ \App\Filament\Pages\CallCenterHub::getUrl() }}">Call center / Voice</a>
                <a href="{{ \App\Filament\Pages\DunningReport::getUrl() }}">Dunning report</a>
            </nav>

            {{-- Main --}}
            <main class="space-y-4">
                @if ($this->activeTab === 'dashboard' || $this->activeTab === 'outage')
                    <section class="isp-comms-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Quick actions</h2>
                        <div class="isp-comms-quick-grid">
                            @foreach ($quickActions as $action)
                                <a href="{{ $action['url'] }}" class="isp-comms-quick isp-comms-glass">
                                    <strong>{{ $action['title'] }}</strong>
                                    <span>{{ $action['desc'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($this->activeTab === 'dashboard')
                    <section class="isp-comms-glass p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-sm font-semibold">Recent delivery log</h2>
                            <a href="{{ \App\Filament\Resources\NotificationLogResource::getUrl() }}" class="text-xs text-primary-600">View all →</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="isp-comms-table">
                                <thead>
                                    <tr><th>Channel</th><th>Event</th><th>Status</th><th>Recipient</th><th>Time</th></tr>
                                </thead>
                                <tbody>
                                    @forelse ($d['recent_logs'] ?? [] as $log)
                                        <tr>
                                            <td>{{ strtoupper($log['channel']) }}</td>
                                            <td>{{ $log['event'] }}</td>
                                            <td>{{ $log['status'] }}</td>
                                            <td>{{ $log['recipient'] }}</td>
                                            <td>{{ $log['at'] }}</td>
                                        </tr>
                                    @empty
                                        <tr><td colspan="5" class="opacity-60">No messages yet</td></tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="isp-comms-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Recent failures</h2>
                        @forelse ($d['recent_failures'] ?? [] as $fail)
                            <div class="isp-comms-alert isp-comms-alert--high">
                                <strong>{{ strtoupper($fail['channel']) }} · {{ $fail['event'] }}</strong>
                                <p class="opacity-80">{{ $fail['recipient'] }} — {{ $fail['error'] }}</p>
                                <span class="text-xs opacity-60">{{ $fail['at'] }}</span>
                            </div>
                        @empty
                            <p class="text-sm opacity-60">No failures — all channels healthy.</p>
                        @endforelse
                    </section>
                @endif

                @if ($this->activeTab === 'campaigns')
                    <section class="isp-comms-glass p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-sm font-semibold">Campaign center</h2>
                            <a href="{{ \App\Filament\Pages\BulkSmsCampaign::getUrl() }}" class="text-xs text-primary-600">New campaign →</a>
                        </div>
                        <table class="isp-comms-table">
                            <thead>
                                <tr><th>Name</th><th>Type</th><th>Channel</th><th>Targets</th><th>Status</th><th>When</th></tr>
                            </thead>
                            <tbody>
                                @forelse ($d['campaigns'] ?? [] as $camp)
                                    <tr>
                                        <td>{{ $camp['name'] }}</td>
                                        <td>{{ $camp['type'] }}</td>
                                        <td>{{ $camp['channel'] }}</td>
                                        <td>{{ number_format($camp['targets']) }}</td>
                                        <td>{{ $camp['status'] }}</td>
                                        <td>{{ $camp['at'] }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="opacity-60">No campaigns yet</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                        @if (count($d['scheduled'] ?? []) > 0)
                            <h3 class="text-xs font-semibold mt-4 mb-2 uppercase opacity-60">Scheduled</h3>
                            @foreach ($d['scheduled'] as $item)
                                <div class="isp-comms-dunning-step isp-comms-dunning-step--on">
                                    {{ $item['name'] }} — {{ $item['scheduled_at'] }} ({{ $item['targets'] }} targets)
                                </div>
                            @endforeach
                        @endif
                    </section>
                @endif

                @if ($this->activeTab === 'templates')
                    @php $tpl = $d['templates_summary'] ?? []; @endphp
                    <section class="isp-comms-glass p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="text-sm font-semibold">Template center</h2>
                            <a href="{{ \App\Filament\Resources\SmsTemplateResource::getUrl() }}" class="text-xs text-primary-600">Manage →</a>
                        </div>
                        <p class="text-sm mb-3">{{ $tpl['enabled'] ?? 0 }} / {{ $tpl['total'] ?? 0 }} templates enabled</p>
                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($tpl['categories'] ?? [] as $cat)
                                <div class="isp-comms-quick isp-comms-glass">
                                    <strong>{{ $cat['label'] }}</strong>
                                    <span>{{ $cat['count'] }} templates</span>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif

                @if ($this->activeTab === 'analytics')
                    <section class="isp-comms-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Delivery analytics (7 days)</h2>
                        <div class="isp-comms-bar-chart mb-2">
                            @foreach ($analytics['labels'] ?? [] as $i => $label)
                                @php
                                    $sent = (int) ($analytics['sent'][$i] ?? 0);
                                    $fail = (int) ($analytics['failed'][$i] ?? 0);
                                    $sentH = (int) round(($sent / $maxBar) * 72);
                                    $failH = (int) round(($fail / max($maxBar, 1)) * 72);
                                @endphp
                                <div class="flex flex-col items-center gap-0.5 flex-1" title="{{ $label }}: {{ $sent }} sent, {{ $fail }} failed">
                                    <div class="isp-comms-bar isp-comms-bar--fail w-full" data-comms-bar="{{ $failH }}" style="height:{{ max(2, $failH) }}px"></div>
                                    <div class="isp-comms-bar w-full" data-comms-bar="{{ $sentH }}" style="height:{{ max(4, $sentH) }}px"></div>
                                    <span class="text-[10px] opacity-60">{{ $label }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="flex flex-wrap gap-3 text-xs mt-3">
                            @foreach ($analytics['by_channel'] ?? [] as $ch => $count)
                                <span class="isp-comms-channel-pill isp-comms-channel-pill--on">{{ strtoupper($ch) }}: {{ number_format($count) }}</span>
                            @endforeach
                        </div>
                        <p class="text-xs opacity-60 mt-3">Open/click rates — Phase 2 (requires link tracking).</p>
                    </section>
                @endif

                @if ($this->activeTab === 'outage')
                    <section class="isp-comms-glass p-4">
                        <h2 class="text-sm font-semibold mb-2">Outage & maintenance broadcast</h2>
                        <p class="text-sm opacity-80 mb-3">Area outage · Fiber cut · OLT/Router maintenance · Emergency network notice.</p>
                        <a href="{{ \App\Filament\Pages\BroadcastOutage::getUrl() }}" class="inline-flex items-center px-4 py-2 rounded-lg bg-primary-600 text-white text-sm">Open broadcast wizard →</a>
                        <p class="text-xs opacity-60 mt-3">GIS targeting: select area on <a href="{{ \App\Filament\Pages\FiberPlantMap::getUrl() }}" class="underline">Network map</a>, then use Bulk SMS with zone/OLT filters.</p>
                    </section>
                @endif

                @if ($this->activeTab === 'billing')
                    <section class="isp-comms-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Billing automation</h2>
                        <p class="text-xs opacity-60 mb-2">Scheduler: <code>{{ $billing['scheduler'] ?? '' }}</code> · {{ $billing['schedule'] ?? '' }}</p>
                        @foreach ($billing['stages'] ?? [] as $stage)
                            <div class="isp-comms-dunning-step {{ ($stage['enabled'] ?? false) ? 'isp-comms-dunning-step--on' : '' }}">
                                {{ $stage['label'] }} ({{ $stage['offset_days'] >= 0 ? '+' : '' }}{{ $stage['offset_days'] }}d)
                            </div>
                        @endforeach
                        <div class="mt-3 flex gap-2 text-xs">
                            <span class="isp-comms-channel-pill {{ ($billing['payment_alerts'] ?? false) ? 'isp-comms-channel-pill--on' : '' }}">Payment alerts</span>
                            <span class="isp-comms-channel-pill {{ ($billing['fup_alerts'] ?? false) ? 'isp-comms-channel-pill--on' : '' }}">FUP alerts</span>
                        </div>
                        <a href="{{ \App\Filament\Pages\DunningReport::getUrl() }}" class="text-xs text-primary-600 mt-3 inline-block">Dunning report →</a>
                    </section>
                    <section class="isp-comms-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Ticket automation</h2>
                        @foreach ($d['ticket_automation'] ?? [] as $ta)
                            <div class="isp-comms-dunning-step {{ ($ta['enabled'] ?? false) ? 'isp-comms-dunning-step--on' : '' }}">
                                <strong>{{ $ta['label'] }}</strong> — {{ $ta['channels'] }}
                            </div>
                        @endforeach
                    </section>
                @endif

                @if ($this->activeTab === 'inbox')
                    <section class="isp-comms-glass p-4">
                        <h2 class="text-sm font-semibold mb-3">Notification inbox</h2>
                        @forelse ($d['inbox'] ?? [] as $item)
                            <div class="isp-comms-inbox-item">
                                <span class="isp-comms-inbox-dot isp-comms-inbox-dot--{{ $item['type'] }}"></span>
                                <div>
                                    <strong>{{ $item['title'] }}</strong>
                                    <p class="opacity-70">{{ $item['detail'] }}</p>
                                    <span class="text-xs opacity-50">{{ strtoupper($item['channel']) }} · {{ $item['status'] }} · {{ $item['at'] }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm opacity-60">Inbox empty</p>
                        @endforelse
                    </section>
                @endif
            </main>

            {{-- Right aside --}}
            <aside class="isp-comms-layout__aside space-y-4">
                @php $gw = $d['sms_gateway'] ?? []; @endphp
                <section class="isp-comms-glass p-4">
                    <h3 class="text-xs font-semibold uppercase opacity-60 mb-2">SMS gateway</h3>
                    <p class="text-lg font-bold">{{ $gw['balance_label'] ?? '—' }}</p>
                    <p class="text-xs opacity-60">{{ $gw['provider_label'] ?? '' }}</p>
                    <p class="text-xs mt-2">Month: {{ number_format($gw['month_sent'] ?? 0) }} sent · {{ number_format($gw['month_failed'] ?? 0) }} failed</p>
                    <a href="{{ \App\Filament\Pages\SmsGatewaySetup::getUrl() }}" class="text-xs text-primary-600 mt-2 inline-block">Gateway →</a>
                </section>

                <section class="isp-comms-glass p-4">
                    <h3 class="text-xs font-semibold uppercase opacity-60 mb-2">Smart alerts</h3>
                    @forelse ($d['smart_alerts'] ?? [] as $alert)
                        <div class="isp-comms-alert isp-comms-alert--{{ $alert['severity'] }}">
                            <strong>{{ $alert['title'] }}</strong>
                            <p class="opacity-80">{{ $alert['detail'] }}</p>
                            @if (! empty($alert['url']))
                                <a href="{{ $alert['url'] }}" class="text-xs underline">View →</a>
                            @endif
                        </div>
                    @empty
                        <p class="text-xs opacity-60">All systems normal</p>
                    @endforelse
                </section>

                <section class="isp-comms-glass p-4 text-xs opacity-60">
                    Updated {{ isset($d['refreshed_at']) ? \Illuminate\Support\Carbon::parse($d['refreshed_at'])->diffForHumans() : 'now' }}
                </section>
            </aside>
        </div>
    </div>

    <div class="isp-comms-mobile-fab">
        <a href="{{ \App\Filament\Pages\SendSms::getUrl() }}">Send</a>
        <a href="{{ \App\Filament\Pages\BulkSmsCampaign::getUrl() }}">Bulk</a>
        <a href="{{ \App\Filament\Pages\BroadcastOutage::getUrl() }}">Alert</a>
    </div>
</x-filament-panels::page>
