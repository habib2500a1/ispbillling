<x-filament-panels::page>
    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <div class="mt-6 rounded-lg border border-cyan-200 bg-cyan-50 p-4 text-sm text-cyan-900 dark:border-cyan-900/50 dark:bg-cyan-950/40 dark:text-cyan-200">
        <p class="font-semibold">Quick guide</p>
        <ol class="mt-2 list-decimal space-y-1 pl-5">
            <li><strong>Both</strong> — add MikroTik routers, enable RADIUS DB, set NAS IP on each router.</li>
            <li><strong>MikroTik only</strong> — Routers list → API credentials → Test MikroTik API.</li>
            <li><strong>RADIUS only</strong> — RADIUS tab → DB host/user/pass → Test RADIUS DB.</li>
            <li><strong>Off</strong> — no live sync (manual / cash ops only).</li>
        </ol>
    </div>
</x-filament-panels::page>
