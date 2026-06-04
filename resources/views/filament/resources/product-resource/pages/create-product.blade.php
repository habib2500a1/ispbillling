@php
    $hubUrl = \App\Filament\Pages\InventoryHub::getUrl();
    $listUrl = \App\Filament\Resources\ProductResource::getUrl();
@endphp

<x-filament-panels::page
    class="isp-inventory-hub-page fi-resource-create-record-page fi-resource-products"
>
    <div class="iv-pro iv-form-shell">
        <header class="iv-form-hero">
            <a href="{{ $listUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Products
            </a>
            <h1 class="iv-form-hero__title">New product</h1>
            <p class="iv-form-hero__sub">Scan barcode, set buy/sell price, optionally receive opening stock into a warehouse.</p>
            <div class="iv-form-hero__links">
                <a href="{{ $hubUrl }}" class="iv-form-hero__link">Inventory center</a>
                <a href="{{ \App\Filament\Resources\InventorySaleResource::getUrl('create') }}" class="iv-form-hero__link">POS sale</a>
            </div>
        </header>

        <div class="iv-form-card">
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
