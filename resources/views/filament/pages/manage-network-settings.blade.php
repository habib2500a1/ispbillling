{!! \App\Support\NetworkStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-network-settings-page">
    <div class="noc-pro space-y-5">
        <header class="mon-hero">
            <h1 class="mon-hero__title">API &amp; RADIUS setup</h1>
            <p class="mon-hero__sub">Configure MikroTik API access, FreeRADIUS database, and network integration mode for your tenant.</p>
        </header>

        <section class="isp-network-form-card">
            <x-filament-panels::form id="form" wire:submit="save">
                {{ $this->form }}

                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>
        </section>

        <div class="net-settings-guide">
            <p class="font-semibold">Quick guide</p>
            <ol class="mt-2 list-decimal space-y-1 pl-5">
                <li><strong>Both</strong> — add MikroTik routers, enable RADIUS DB, set NAS IP on each router.</li>
                <li><strong>MikroTik only</strong> — Routers list → API credentials → Test MikroTik API.</li>
                <li><strong>RADIUS only</strong> — RADIUS tab → DB host/user/pass → Test RADIUS DB.</li>
                <li><strong>Off</strong> — no live sync (manual / cash ops only).</li>
            </ol>
        </div>
    </div>
</x-filament-panels::page>
