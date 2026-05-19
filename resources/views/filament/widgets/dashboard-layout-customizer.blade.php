<x-filament-widgets::widget>
    <details class="isp-layout-customizer">
        <summary class="isp-layout-customizer__summary">
            <x-filament::icon icon="heroicon-o-adjustments-horizontal" class="h-4 w-4" />
            Customize dashboard layout (drag order via checkboxes)
        </summary>
        <form wire:submit="saveLayout" class="isp-layout-customizer__body">
            <p class="text-xs text-gray-500 dark:text-gray-400 mb-3">Select widgets to show. Order follows selection order.</p>
            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->widgetOptions() as $class => $label)
                    <label class="isp-layout-check">
                        <input type="checkbox" wire:model="selected" value="{{ $class }}" class="rounded border-gray-300">
                        <span>{{ $label }}</span>
                    </label>
                @endforeach
            </div>
            <label class="mt-3 flex items-center gap-2 text-sm">
                <input type="checkbox" wire:model="compact" class="rounded border-gray-300">
                Compact spacing
            </label>
            <button type="submit" class="mt-4 isp-quick-pill isp-quick-pill-primary text-sm">Save layout</button>
        </form>
    </details>
</x-filament-widgets::widget>
