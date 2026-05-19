@php
    $pollSeconds = (int) config('bandwidth.subscriber_chart_poll_seconds', 30);
    $collectOnPoll = config('bandwidth.subscriber_view_collect_on_poll', false);
@endphp

<x-filament-panels::page>
    <div
        class="space-y-4"
        @if ($pollSeconds > 0 && $collectOnPoll)
            wire:poll.{{ $pollSeconds }}s="refreshLiveData"
        @endif
    >
        @if ($customer === null)
            <div class="rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
                <p class="font-semibold">No subscriber selected</p>
                <p class="mt-2 text-sm">
                    Open this page from
                    <a href="{{ \App\Filament\Pages\OnlineClientsMonitoring::getUrl() }}" class="font-medium underline">
                        Online clients
                    </a>
                    and click <strong>Traffic graph</strong> on a connected user.
                </p>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4 text-sm text-gray-600 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400">
                <p>
                    <span class="font-semibold text-gray-900 dark:text-white">{{ $customer->name }}</span>
                    · {{ $customer->pppLoginName() }}
                    ·
                    @if ($customer->isPppOnline())
                        <span class="text-emerald-600 dark:text-emerald-400">Connected</span>
                    @else
                        <span class="text-gray-500">Offline</span>
                    @endif
                </p>
                <p class="mt-1">
                    Live graph polls MikroTik every {{ $pollSeconds }}s while this page is open.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
