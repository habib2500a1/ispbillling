@php
    $smsGatewayUrl = \Illuminate\Support\Facades\Route::has('filament.admin.pages.sms-gateway')
        ? \App\Filament\Pages\SmsGatewaySetup::getUrl()
        : url('/admin/sms-gateway');

    $channels = [
        ['key' => 'email', 'label' => 'Email', 'on' => (bool) config('notifications.email.enabled')],
        ['key' => 'sms', 'label' => 'SMS', 'on' => (bool) config('notifications.sms.enabled') && filled(config('notifications.sms.api_key')) && (config('notifications.sms.provider') !== 'khudebarta' || filled(config('notifications.sms.secret_key')))],
        ['key' => 'whatsapp', 'label' => 'WhatsApp', 'on' => (bool) config('notifications.whatsapp.enabled')],
        ['key' => 'telegram', 'label' => 'Telegram (ops)', 'on' => (bool) config('notifications.telegram.enabled')],
    ];
    $events = [
        ['label' => 'Payment success', 'on' => (bool) config('notifications.events.payment_success.enabled')],
        ['label' => 'Due reminders', 'on' => (bool) config('notifications.events.invoice_due.enabled')],
        ['label' => 'Outage broadcast', 'on' => true],
        ['label' => 'Portal OTP', 'on' => (bool) config('portal.otp.enabled')],
    ];
@endphp

<x-filament-panels::page>
    @php
        $enabledChannels = collect($channels)->where('on', true)->count();
        $enabledEvents = collect($events)->where('on', true)->count();
        $statCards = [
            ['label' => 'Channels live', 'value' => (string) $enabledChannels, 'hint' => count($channels).' configured surfaces', 'class' => 'isp-hub-stat--teal'],
            ['label' => 'Automation events', 'value' => (string) $enabledEvents, 'hint' => 'Receipt, due, outage, OTP', 'class' => 'isp-hub-stat--amber'],
            ['label' => 'Ops channel', 'value' => config('notifications.telegram.enabled') ? 'Telegram on' : 'Telegram off', 'hint' => 'Internal broadcast path', 'class' => 'isp-hub-stat--sky'],
        ];
    @endphp

    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Messaging workspace"
            title="SMS & notification system"
            description="Multi-channel alerts across SMS gateways, WhatsApp Cloud API, Telegram ops channel, and email with delivery logs."
            class="isp-hub-hero--teal"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ $enabledChannels }} channels active</span>
                    <span class="isp-hub-section__meta">{{ $enabledEvents }} automated events</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Channel status</h2>
                    <p class="isp-hub-section__desc">Current notification transport availability across customer and operations messaging surfaces.</p>
                </div>
                <span class="isp-hub-section__meta">{{ count($channels) }} channels</span>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach ($channels as $ch)
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $ch['on'] ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        {{ $ch['label'] }}{{ $ch['on'] ? ' ✓' : '' }}
                    </span>
                @endforeach
            </div>
        </section>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ $smsGatewayUrl }}" class="group rounded-xl border border-emerald-200 bg-emerald-50/50 p-5 shadow-sm transition hover:border-emerald-400 dark:border-emerald-900/50 dark:bg-emerald-950/20">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-500/20 dark:text-emerald-400">
                        <x-heroicon-o-chat-bubble-left-ellipsis class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-emerald-600 dark:text-white">SMS Gateway</p>
                        <p class="mt-0.5 text-sm text-gray-500">Balance, usage stats & credentials</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Pages\ManageNotifications::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                        <x-heroicon-o-cog-6-tooth class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white">All channel settings</p>
                        <p class="mt-0.5 text-sm text-gray-500">Email, WhatsApp, Telegram, templates</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\SmsDeliveryReportResource::getUrl() }}" class="group rounded-xl border border-violet-200 bg-violet-50/50 p-5 shadow-sm transition hover:border-violet-400 dark:border-violet-900/50 dark:bg-violet-950/20">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-500/20 dark:text-violet-400">
                        <x-heroicon-o-signal class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-violet-600 dark:text-white">SMS delivery (DLR)</p>
                        <p class="mt-0.5 text-sm text-gray-500">KhudeBarta delivery status</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Resources\NotificationLogResource::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-sky-100 text-sky-600 dark:bg-sky-500/20 dark:text-sky-400">
                        <x-heroicon-o-clipboard-document-list class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white">Delivery log</p>
                        <p class="mt-0.5 text-sm text-gray-500">Sent, failed, skipped per channel</p>
                    </div>
                </div>
            </a>
            <a href="{{ \App\Filament\Pages\BroadcastOutage::getUrl() }}" class="group rounded-xl border border-amber-200 bg-amber-50/50 p-5 shadow-sm transition hover:border-amber-400 dark:border-amber-900/50 dark:bg-amber-950/20">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-700 dark:bg-amber-500/20 dark:text-amber-300">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-amber-950 dark:text-amber-100">Outage broadcast</p>
                        <p class="mt-0.5 text-sm text-amber-800 dark:text-amber-300">Notify all active subscribers</p>
                    </div>
                </div>
            </a>
        </div>

        <section class="isp-ops-panel">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">Automated events</h3>
                    <p class="isp-ops-panel__desc">Core notification triggers currently enabled for billing, outage, and portal authentication flows.</p>
                </div>
                <span class="isp-ops-pill isp-ops-pill--ok">{{ $enabledEvents }} active</span>
            </div>
            <div class="p-4 pt-0">
            <ul class="mt-3 grid gap-2 sm:grid-cols-2">
                @foreach ($events as $ev)
                    <li class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                        <span class="h-2 w-2 rounded-full {{ $ev['on'] ? 'bg-success-500' : 'bg-gray-300 dark:bg-gray-600' }}"></span>
                        {{ $ev['label'] }}
                    </li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-gray-500">
                Scheduler: <span class="font-mono">isp:send-invoice-due-reminders</span> (daily) · Payment alerts fire when status becomes completed.
            </p>
            </div>
        </section>
    </div>
</x-filament-panels::page>
