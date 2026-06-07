<div class="isp-layout-customizer isp-layout-customizer--drawer">
    <header class="isp-layout-customizer__head">
        <h2 class="isp-layout-customizer__title">Customize dashboard</h2>
        <p class="isp-layout-customizer__hint">
            Choose widgets, reorder sections, and toggle compact spacing.
        </p>
    </header>
    <form wire:submit="saveDashboardLayout" class="isp-layout-customizer__body">
        <div class="isp-layout-customizer__list">
            @foreach ($this->layoutRows() as $row)
                <div class="isp-layout-check">
                    <label class="isp-layout-check__label">
                        <input
                            type="checkbox"
                            @checked($row['enabled'])
                            wire:click="toggleLayoutWidget(@js($row['class']))"
                            class="rounded border-gray-300"
                        >
                        <span class="truncate">{{ $row['label'] }}</span>
                    </label>
                    @if ($row['enabled'])
                        <span class="isp-layout-customizer__actions">
                            <button
                                type="button"
                                wire:click="moveLayoutWidgetUp(@js($row['class']))"
                                class="isp-layout-customizer__move"
                                title="Move up"
                                aria-label="Move up"
                            >↑</button>
                            <button
                                type="button"
                                wire:click="moveLayoutWidgetDown(@js($row['class']))"
                                class="isp-layout-customizer__move"
                                title="Move down"
                                aria-label="Move down"
                            >↓</button>
                        </span>
                    @endif
                </div>
            @endforeach
        </div>
        <label class="isp-layout-customizer__compact">
            <input type="checkbox" wire:model="layoutCompact" class="rounded border-gray-300">
            Compact spacing
        </label>
        <button type="submit" class="isp-quick-pill isp-quick-pill-primary isp-layout-customizer__save">Save layout</button>
    </form>
</div>
