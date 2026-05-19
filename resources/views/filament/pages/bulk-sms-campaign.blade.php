<x-filament-panels::page>
    <div class="grid gap-6 lg:grid-cols-2">
        <div>
            <x-filament-panels::form id="bulk-sms-form" wire:submit="sendCampaign">
                {{ $this->form }}
                <x-filament-panels::form.actions
                    :actions="$this->getCachedFormActions()"
                    :full-width="$this->hasFullWidthFormActions()"
                />
            </x-filament-panels::form>
        </div>
        <div>
            <h3 class="mb-3 text-sm font-semibold text-gray-700 dark:text-gray-300">Campaign history</h3>
            {{ $this->table }}
        </div>
    </div>
</x-filament-panels::page>
