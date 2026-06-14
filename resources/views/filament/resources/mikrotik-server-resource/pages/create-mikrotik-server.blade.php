@php
    $listUrl = \App\Filament\Resources\MikrotikServerResource::getUrl('index');
    $hubUrl = \App\Filament\Pages\NetworkIntelligenceHub::getUrl();
@endphp

{!! \App\Support\NetworkStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-network-noc-page">
    <div class="net-noc-pro space-y-4">
        <header class="net-profile-hero net-profile-hero--compact">
            <p class="net-profile-hero__eyebrow">Network · Add router</p>
            <h1 class="net-profile-hero__title">Register MikroTik server</h1>
            <p class="net-profile-hero__sub">
                Router connection details for API access, PPPoE sync, monitoring, and subscriber import.
            </p>
            <div class="net-profile-tabs" aria-hidden="true">
                <span class="net-profile-tab net-profile-tab--active">Identity</span>
                <span class="net-profile-tab">API</span>
                <span class="net-profile-tab">PPP defaults</span>
            </div>
        </header>

        <section class="net-profile-form isp-network-router-form">
            <x-filament-panels::form wire:submit.prevent="create">
                {{ $this->form }}
                <div class="net-profile-form__actions">
                    <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
                </div>
            </x-filament-panels::form>
        </section>

        <p class="net-profile-notes">
            <strong>Preserved workflow</strong>
            All existing MikroTik sync, import, and probe actions remain unchanged after save.
            <a href="{{ $listUrl }}" style="color:var(--net-blue);">Back to routers list</a>
            · <a href="{{ $hubUrl }}" style="color:var(--net-blue);">Network center</a>
        </p>
    </div>
</x-filament-panels::page>
