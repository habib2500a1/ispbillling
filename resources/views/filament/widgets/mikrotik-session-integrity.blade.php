<x-filament-widgets::widget>
    <x-filament::section heading="MikroTik session integrity" icon="heroicon-o-signal">
        <div class="flex flex-wrap items-center gap-4 text-sm">
            <span class="rounded-lg bg-amber-50 px-3 py-1 font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-200">
                {{ $open }} open alert{{ $open === 1 ? '' : 's' }}
            </span>
            @if ($critical > 0)
                <span class="rounded-lg bg-red-50 px-3 py-1 font-semibold text-red-800 dark:bg-red-950 dark:text-red-200">
                    {{ $critical }} critical
                </span>
            @endif
        </div>

        @if ($items->isEmpty())
            <p class="mt-3 text-sm text-gray-500">No active session mismatches.</p>
        @else
            <ul class="mt-4 divide-y divide-gray-200 text-sm dark:divide-gray-700">
                @foreach ($items as $alert)
                    <li class="flex flex-wrap items-start justify-between gap-2 py-2">
                        <div class="min-w-0 flex-1">
                            <span class="font-semibold">{{ str_replace('_', ' ', $alert->alert_type) }}</span>
                            @if ($alert->customer)
                                <span class="text-gray-500">· {{ $alert->customer->name }} ({{ $alert->customer->customer_code }})</span>
                            @endif
                            <p class="text-gray-600 dark:text-gray-400">{{ $alert->message }}</p>
                            @if ($alert->customer_id)
                                <div class="mt-1 flex flex-wrap gap-2">
                                    <button type="button" wire:click="suspendAlert({{ $alert->id }})" wire:confirm="Suspend this subscriber on MikroTik?"
                                        class="text-xs font-semibold text-rose-600 hover:underline">Suspend</button>
                                    <button type="button" wire:click="resolveAlert({{ $alert->id }})"
                                        class="text-xs font-semibold text-gray-500 hover:underline">Dismiss</button>
                                    <a href="{{ \App\Filament\Resources\CustomerResource::getUrl('edit', ['record' => $alert->customer_id]) }}" class="text-xs font-semibold text-violet-600 hover:underline">Open</a>
                                </div>
                            @endif
                        </div>
                        <span class="text-xs text-gray-400 shrink-0">{{ $alert->created_at?->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
