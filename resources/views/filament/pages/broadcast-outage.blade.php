<x-filament-panels::page>
    <div class="mb-4">
        <a href="{{ \App\Filament\Pages\NotificationsHub::getUrl() }}" class="text-sm text-primary-600 hover:underline dark:text-primary-400">
            ← Back to notifications hub
        </a>
    </div>
    <x-filament-panels::form id="form" wire:submit="send">
        {{ $this->form }}
        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
