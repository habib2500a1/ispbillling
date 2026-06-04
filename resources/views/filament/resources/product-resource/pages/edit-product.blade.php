@php
    $record = $this->getRecord();
    $listUrl = \App\Filament\Resources\ProductResource::getUrl();
@endphp

<x-filament-panels::page
    class="isp-inventory-hub-page fi-resource-edit-record-page fi-resource-products"
>
    <div class="iv-pro iv-form-shell">
        <header class="iv-form-hero">
            <a href="{{ $listUrl }}" class="iv-form-hero__back">
                <x-filament::icon icon="heroicon-m-arrow-left" class="h-4 w-4" />
                Products
            </a>
            @if ($record->imageUrl())
                <img src="{{ $record->imageUrl() }}" alt="" class="iv-form-hero__thumb" width="72" height="72">
            @endif
            <h1 class="iv-form-hero__title">{{ $record->name }}</h1>
            <p class="iv-form-hero__sub">
                @if ($record->sku)
                    <span class="font-mono">{{ $record->sku }}</span>
                @endif
                @if ($record->barcode)
                    · Barcode <span class="font-mono">{{ $record->barcode }}</span>
                @endif
                · Stock {{ number_format((int) $record->stock_qty) }} {{ $record->unit }}
            </p>
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
