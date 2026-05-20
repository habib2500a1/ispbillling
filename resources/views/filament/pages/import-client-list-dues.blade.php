<x-filament-panels::page>
    <div class="max-w-2xl space-y-6">
        <x-filament::section>
            <x-slot name="heading">Upload Client List PDF / Excel</x-slot>
            <x-slot name="description">
                Same export as ISP Digital → Client List (PDF or Excel). Due amounts will match the file.
            </x-slot>

            <form wire:submit="importFromFile" class="space-y-4">
                {{ $this->form }}

                <x-filament::button type="submit" icon="heroicon-o-arrow-up-tray">
                    Import dues from file
                </x-filament::button>
            </form>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Or sync live from ISP Digital</x-slot>
            <x-slot name="description">
                Pulls the same billing grid as the Client List PDF (no upload needed).
            </x-slot>

            <x-filament::button
                wire:click="syncFromIspDigital"
                color="gray"
                icon="heroicon-o-arrow-path"
            >
                Sync from ISP Digital now
            </x-filament::button>
        </x-filament::section>
    </div>
</x-filament-panels::page>
