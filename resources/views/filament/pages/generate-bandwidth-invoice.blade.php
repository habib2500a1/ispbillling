<x-filament-panels::page class="isp-bw-generate-page">
    <div class="isp-bw-generate-card">
        <div class="mb-4">
            <p class="text-xs font-bold uppercase tracking-widest text-sky-600">BW Client</p>
            <h2 class="text-xl font-bold text-gray-900 dark:text-white">Generate bandwidth invoice</h2>
            <p class="mt-1 text-sm text-gray-500">Pick client and period — amount defaults from profile total.</p>
        </div>
        <form wire:submit="generate">
            {{ $this->form }}
            <div class="mt-6 flex flex-wrap gap-2">
                <x-filament::button type="submit" icon="heroicon-o-document-plus">
                    Generate invoice
                </x-filament::button>
                <x-filament::button
                    type="button"
                    color="gray"
                    tag="a"
                    :href="\App\Filament\Resources\BandwidthClientResource::getUrl()"
                >
                    Back to clients
                </x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
