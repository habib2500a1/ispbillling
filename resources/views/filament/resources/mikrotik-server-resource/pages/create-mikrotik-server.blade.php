<x-filament-panels::page class="isp-network-page">
    <div class="space-y-5">
        <section class="isp-network-hero isp-network-hero--compact">
            <div class="isp-network-hero__main">
                <p class="isp-network-hero__eyebrow">Network</p>
                <h2 class="isp-network-hero__title">Add router</h2>
                <p class="isp-network-hero__sub">
                    Register router connection details for API and monitoring access.
                </p>
            </div>
        </section>

        <section class="isp-network-form-card">
            <x-filament-panels::form wire:submit="create">
                {{ $this->form }}
                <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
            </x-filament-panels::form>
        </section>
    </div>
</x-filament-panels::page>
