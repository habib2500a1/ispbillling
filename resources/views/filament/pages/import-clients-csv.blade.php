<x-filament-panels::page class="isp-network-page">
    <div class="space-y-5">
        <section class="isp-network-hero">
            <div class="isp-network-hero__main">
                <p class="isp-network-hero__eyebrow">Network</p>
                <h2 class="isp-network-hero__title">Import client CSV</h2>
                <p class="isp-network-hero__sub">
                    Bulk-import PPP subscribers from Excel or CSV and link them to a MikroTik router.
                </p>
            </div>
            <div class="isp-network-hero__actions">
                <x-filament::button type="button" wire:click="downloadSample" icon="heroicon-o-document-arrow-down" color="gray" size="sm">
                    Download sample
                </x-filament::button>
            </div>
        </section>

        <section class="isp-network-form-card">
            <form wire:submit="submitImport" class="space-y-4">
                {{ $this->form }}
                <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                    Start import
                </x-filament::button>
            </form>
        </section>
    </div>
</x-filament-panels::page>
