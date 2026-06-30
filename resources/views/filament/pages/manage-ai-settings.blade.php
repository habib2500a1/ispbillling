<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-950 dark:border-indigo-900/50 dark:bg-indigo-950/30 dark:text-indigo-100">
        <p class="font-semibold">Enterprise AI — tenant settings</p>
        <p class="mt-1">Enable copilot, customer AI, optional LLM, Bengali replies, quotas, and human-in-the-loop actions. Infrastructure keys can still be set in <code class="text-xs">.env</code>; tenant overrides apply here.</p>
    </div>

    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
