<x-filament-panels::page class="fi-dashboard-page isp-dashboard-v2">
    @include('filament.partials.dashboard-header')

    <div
        class="isp-dash-layout-panel"
        x-data="{ customizerOpen: false }"
        @open-layout-customizer.window="customizerOpen = true"
    >
        <div
            class="isp-dash-layout-panel__backdrop"
            x-show="customizerOpen"
            x-transition.opacity
            @click="customizerOpen = false"
            aria-hidden="true"
        ></div>
        <aside
            class="isp-dash-layout-panel__drawer"
            x-show="customizerOpen"
            x-transition:enter="isp-dash-layout-panel__drawer--enter"
            x-transition:leave="isp-dash-layout-panel__drawer--leave"
            @keydown.escape.window="customizerOpen = false"
            role="dialog"
            aria-label="Customize dashboard layout"
        >
            @include('filament.partials.dashboard-layout-customizer')
        </aside>
    </div>

    <x-filament-widgets::widgets
        :columns="$this->getColumns()"
        :data="
            [
                ...(property_exists($this, 'filters') ? ['filters' => $this->filters] : []),
                ...$this->getWidgetData(),
            ]
        "
        :widgets="$this->getVisibleWidgets()"
    />
</x-filament-panels::page>
