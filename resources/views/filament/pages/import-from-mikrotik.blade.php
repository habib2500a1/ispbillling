<x-filament-panels::page class="isp-network-page">
    <div class="space-y-5">
        <section class="isp-network-hero">
            <div class="isp-network-hero__main">
                <p class="isp-network-hero__eyebrow">Network</p>
                <h2 class="isp-network-hero__title">Import from MikroTik</h2>
                <p class="isp-network-hero__sub">
                    Pull PPP secrets from a live router and create billing subscribers for selected users.
                </p>
            </div>
        </section>

        <section class="isp-network-form-card">
            <form wire:submit="submitImport" class="space-y-4">
                {{ $this->form }}
                <x-filament::button type="submit" icon="heroicon-o-arrow-down-tray">
                    Import selected users
                </x-filament::button>
            </form>
        </section>
    </div>
</x-filament-panels::page>
