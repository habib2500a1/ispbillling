<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-900/50 dark:bg-sky-950/30 dark:text-sky-100">
        <p class="font-semibold">500k-scale subscriber search — zero extra .env keys</p>
        <p class="mt-1">Meilisearch key is <strong>auto-generated from APP_KEY</strong>. Enable here, deploy Docker stack, then click <strong>Re-index all subscribers</strong> once (or let post-deploy auto-index run).</p>
        <p class="mt-1 text-xs">Used by: Support ticket create · Bill collection · Mobile staff search · Ctrl+K command palette</p>
    </div>

    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
