<x-filament-panels::page>
    <div class="mx-auto max-w-3xl space-y-6">
        <x-isp.hub-hero
            eyebrow="Verification"
            title="Two-factor authentication"
            description="Enter the 6-digit code from your authenticator app, or use one of your saved recovery codes."
            class="isp-hub-hero--violet"
        />

        <section class="isp-ops-panel">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">Verify your account</h3>
                    <p class="isp-ops-panel__desc">Complete the second step to continue into the admin panel.</p>
                </div>
                <span class="isp-ops-pill isp-ops-pill--warn">2FA required</span>
            </div>
            <form wire:submit="verify" class="p-4 pt-0">
                {{ $this->form }}
                <x-filament::button type="submit" class="mt-4 w-full">Verify</x-filament::button>
            </form>
        </section>
    </div>
</x-filament-panels::page>
