@php
    $hubUrl = \App\Filament\Pages\InventoryHub::getUrl();
    $productsUrl = \App\Filament\Resources\ProductResource::getUrl();
@endphp

<x-filament-panels::page
    class="isp-inventory-hub-page fi-resource-create-record-page fi-resource-inventory-sales iv-pos-page"
>
    <div class="iv-pro iv-form-shell">
        <header class="iv-form-hero">
            <a href="{{ $hubUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Inventory center
            </a>
            <h1 class="iv-form-hero__title">New sale (POS)</h1>
            <p class="iv-form-hero__sub">Scan barcode · pick products with photos · warehouse stock · instant receipt.</p>
            <div class="iv-form-hero__links">
                <a href="{{ $productsUrl }}" class="iv-form-hero__link">Products</a>
                <a href="{{ \App\Filament\Resources\InventorySaleResource::getUrl() }}" class="iv-form-hero__link">Sales history</a>
            </div>
        </header>

        <div class="iv-form-card iv-pos-card">
            <x-filament-panels::form
                id="form"
                :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                wire:submit="create"
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
