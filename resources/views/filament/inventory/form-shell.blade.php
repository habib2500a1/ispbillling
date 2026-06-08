<script src="{{ asset('js/inventory-asset-intelligence.js') }}?v={{ @filemtime(public_path('js/inventory-asset-intelligence.js')) ?: 1 }}" defer data-cfasync="false"></script>

<x-filament-panels::page @class(['isp-inventory-hub-page', 'fi-resource-create-record-page', 'fi-resource-edit-record-page', 'isp-inventory-form-page'])>
    <div class="iv-pro iv-form-shell">
        <header class="iv-form-hero">
            <a href="{{ $inventoryFormBackUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                {{ $inventoryFormBackLabel }}
            </a>
            <h1 class="iv-form-hero__title">{{ $inventoryFormTitle }}</h1>
            <p class="iv-form-hero__sub">{{ $inventoryFormSubtitle }}</p>
        </header>

        <div class="iv-form-card">
            <x-filament-panels::form
                id="form"
                :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                wire:submit="save"
            >
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>
        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
