{!! \App\Support\SupportStyles::html() !!}
{!! \App\Support\SupportStyles::navigatedScript() !!}
<script src="{{ asset('js/support-ticket-create.js') }}?v={{ @filemtime(public_path('js/support-ticket-create.js')) ?: 1 }}" defer data-cfasync="false"></script>

<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'isp-support-ticket-create',
    ])
>
    <div wire:key="support-subscriber-search-{{ $this->getId() }}">
        @include('filament.resources.support-ticket-resource.partials.subscriber-search')
    </div>

    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
        wire:submit="create"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
