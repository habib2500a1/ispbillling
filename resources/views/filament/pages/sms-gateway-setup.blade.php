@php
    $stats = $this->getSmsStats();
    $balanceDisplay = $stats['balance'] !== null
        ? number_format((float) $stats['balance'], 1)
        : ($stats['balance_label'] !== 'N/A' ? $stats['balance_label'] : '—');
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-center justify-between gap-2 text-sm text-gray-500 dark:text-gray-400">
            <span>SMS Service &rsaquo; SMS Gateway Setup</span>
            @if ($stats['balance_fetched_at'])
                <span>Balance checked: {{ $stats['balance_fetched_at'] }}</span>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="overflow-hidden rounded-lg bg-emerald-600 text-white shadow-md">
                <div class="flex items-start gap-4 p-5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-emerald-500/80">
                        <x-heroicon-o-envelope class="h-8 w-8" />
                    </span>
                    <div>
                        <p class="text-lg font-semibold opacity-95">SMS Balance</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums">{{ $balanceDisplay }}</p>
                    </div>
                </div>
                <div class="bg-emerald-700/90 px-5 py-2.5 text-sm font-medium">
                    Total SMS remaining balance
                    @if ($stats['balance_error'])
                        <span class="mt-1 block text-xs font-normal opacity-90" title="{{ $stats['balance_error'] }}">
                            {{ \Illuminate\Support\Str::limit($stats['balance_error'], 80) }}
                        </span>
                    @endif
                </div>
            </div>

            <div class="overflow-hidden rounded-lg bg-sky-600 text-white shadow-md">
                <div class="flex items-start gap-4 p-5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-sky-500/80">
                        <x-heroicon-o-check-badge class="h-8 w-8" />
                    </span>
                    <div>
                        <p class="text-lg font-semibold opacity-95">Today's Send</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums">{{ number_format($stats['today_sent']) }}</p>
                    </div>
                </div>
                <div class="bg-sky-700/90 px-5 py-2.5 text-sm font-medium">Total SMS sent today</div>
            </div>

            <div class="overflow-hidden rounded-lg bg-amber-500 text-white shadow-md">
                <div class="flex items-start gap-4 p-5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-amber-400/80">
                        <x-heroicon-o-clock class="h-8 w-8" />
                    </span>
                    <div>
                        <p class="text-lg font-semibold opacity-95">This Month Send</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums">{{ number_format($stats['month_sent']) }}</p>
                    </div>
                </div>
                <div class="bg-amber-600/90 px-5 py-2.5 text-sm font-medium">Total SMS sent this month</div>
            </div>

            <div class="overflow-hidden rounded-lg bg-rose-600 text-white shadow-md">
                <div class="flex items-start gap-4 p-5">
                    <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded bg-rose-500/80">
                        <x-heroicon-o-x-circle class="h-8 w-8" />
                    </span>
                    <div>
                        <p class="text-lg font-semibold opacity-95">This Month Failed</p>
                        <p class="mt-1 text-3xl font-bold tabular-nums">{{ number_format($stats['month_failed']) }}</p>
                    </div>
                </div>
                <div class="bg-rose-700/90 px-5 py-2.5 text-sm font-medium">Total SMS sending failed this month</div>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 bg-slate-800 px-5 py-3 dark:border-gray-700">
                <h3 class="text-base font-semibold text-white">SMS Settings</h3>
                <p class="mt-0.5 text-xs text-slate-300">
                    Provider: <strong>{{ $stats['provider_label'] }}</strong>
                    · {{ $stats['sms_enabled'] ? 'SMS enabled' : 'SMS disabled' }}
                </p>
            </div>
            <div class="p-5">
                <x-filament-panels::form id="form" wire:submit="save">
                    {{ $this->form }}

                    <x-filament-panels::form.actions
                        :actions="$this->getCachedFormActions()"
                        :full-width="$this->hasFullWidthFormActions()"
                        class="mt-6"
                    />
                </x-filament-panels::form>
            </div>
        </div>

        <div class="flex flex-wrap gap-3 text-sm">
            <a href="{{ \App\Filament\Resources\NotificationLogResource::getUrl() }}" class="text-primary-600 hover:underline dark:text-primary-400">
                Delivery log
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ \App\Filament\Resources\SmsDeliveryReportResource::getUrl() }}" class="text-primary-600 hover:underline dark:text-primary-400">
                SMS DLR reports
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ \App\Filament\Pages\ManageNotifications::getUrl() }}" class="text-primary-600 hover:underline dark:text-primary-400">
                All notification channels & templates
            </a>
            <span class="text-gray-300">|</span>
            <a href="{{ \App\Filament\Pages\BulkSmsCampaign::getUrl() }}" class="text-primary-600 hover:underline dark:text-primary-400">
                Bulk SMS
            </a>
        </div>
    </div>
</x-filament-panels::page>
