<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero isp-reseller-hero--compact">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Add reseller</h2>
                <p class="isp-reseller-hero__sub">
                    Create a partner account with commission, wallet, territories, and portal login.
                </p>
            </div>
        </section>

        <section class="isp-reseller-form-card">
            <x-filament-panels::form wire:submit="create">
                {{ $this->form }}
                <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
            </x-filament-panels::form>
        </section>
    </div>
</x-filament-panels::page>
