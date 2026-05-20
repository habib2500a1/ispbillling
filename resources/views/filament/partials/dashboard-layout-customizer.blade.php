<details class="isp-layout-customizer mb-4">
    <summary class="isp-layout-customizer__summary">
        <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-4 w-4" />
        Customize dashboard layout
    </summary>
    <form wire:submit="saveDashboardLayout" class="isp-layout-customizer__body">
        <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">
            Check widgets to show. Use arrows to change order.
        </p>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($this->layoutRows() as $row)
                <div class="isp-layout-check flex items-center justify-between gap-2">
                    <label class="flex min-w-0 flex-1 items-center gap-2">
                        <input
                            type="checkbox"
                            @checked($row['enabled'])
                            wire:click="toggleLayoutWidget(@js($row['class']))"
                            class="rounded border-gray-300"
                        >
                        <span class="truncate">{{ $row['label'] }}</span>
                    </label>
                    @if ($row['enabled'])
                        <span class="isp-layout-customizer__actions flex shrink-0 gap-0.5">
                            <button
                                type="button"
                                wire:click="moveLayoutWidgetUp(@js($row['class']))"
                                class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                                title="Move up"
                                aria-label="Move up"
                            >↑</button>
                            <button
                                type="button"
                                wire:click="moveLayoutWidgetDown(@js($row['class']))"
                                class="rounded px-1.5 py-0.5 text-xs text-gray-500 hover:bg-gray-100 dark:hover:bg-gray-800"
                                title="Move down"
                                aria-label="Move down"
                            >↓</button>
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
        <label class="mt-3 flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="layoutCompact" class="rounded border-gray-300">
            Compact spacing
        </label>
        <button type="submit" class="mt-4 isp-quick-pill isp-quick-pill-primary text-sm">Save layout</button>
    </form>
</details>
