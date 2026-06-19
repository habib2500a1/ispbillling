<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-950 dark:border-emerald-900/50 dark:bg-emerald-950/30 dark:text-emerald-100">
        <p class="font-semibold">Performance hub — no .env editing</p>
        <p class="mt-1">Subscriber page speed, polling load, CSS bundles, and scheduler safety — all from here. Infrastructure (DB, Redis, APP_KEY) stays in <code class="text-xs">.env</code> only.</p>
    </div>

    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
