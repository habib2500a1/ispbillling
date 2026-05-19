@php
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
    <div class="space-y-6">
        <div class="rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 via-white to-amber-50/50 p-6 shadow-sm dark:border-primary-900/40 dark:from-primary-950/50 dark:via-gray-900 dark:to-gray-900">
            <h2 class="text-xl font-semibold text-gray-900 dark:text-white">SMS & notification system</h2>
            <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">
                Multi-channel alerts: SMS gateways (BulkSMSBD, SSL Wireless), WhatsApp Cloud API, Telegram ops channel, and email.
                Payment receipts, invoice due reminders, outage broadcasts, and portal OTP — all configurable with delivery logs.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($channels as $ch)
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $ch['on'] ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        {{ $ch['label'] }}{{ $ch['on'] ? ' ✓' : '' }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Pages\ManageNotifications::getUrl() }}" class="group rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 hover:shadow-md dark:border-gray-700 dark:bg-gray-900">
                <div class="flex items-center gap-3">
                    <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary-600 dark:bg-primary-500/20 dark:text-primary-400">
                        <x-heroicon-o-cog-6-tooth class="h-5 w-5" />
                    </span>
                    <div>
                        <p class="font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white">Channel settings</p>
                        <p class="mt-0.5 text-sm text-gray-500">Gateways, templates, event toggles</p>
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

        <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-semibold text-gray-900 dark:text-white">Automated events</p>
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
    </div>
</x-filament-panels::page>
