@php
    $statCards = [
        ['label' => 'IP policy', 'value' => 'Tenant scoped', 'hint' => 'Allowed admin sources only', 'class' => 'isp-hub-stat--amber'],
        ['label' => '2FA policy', 'value' => 'Team control', 'hint' => 'Can be enforced for staff', 'class' => 'isp-hub-stat--violet'],
        ['label' => 'Security area', 'value' => 'Admin login', 'hint' => 'Panel access protection', 'class' => 'isp-hub-stat--rose'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Access protection"
            title="Login security"
            description="Control which IPs may access the admin panel and whether two-factor authentication is mandatory for your team."
            class="isp-hub-hero--amber"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">Tenant security policy</span>
                    <span class="isp-hub-section__meta">Admin-only controls</span>
                </div>
                <a href="{{ \App\Filament\Pages\StaffControlHub::getUrl() }}" class="isp-quick-pill">Staff control hub</a>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <section class="isp-ops-panel">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">Security policy</h3>
                    <p class="isp-ops-panel__desc">Update admin login IP rules and team-level two-factor requirements from one place.</p>
                </div>
                <span class="isp-ops-pill isp-ops-pill--warn">Sensitive</span>
            </div>
            <form wire:submit="save" class="p-4 pt-0">
                {{ $this->form }}
                <div class="mt-6">
                    <x-filament::button type="submit">Save settings</x-filament::button>
                </div>
            </form>
        </section>
    </div>
</x-filament-panels::page>
