@php
    $hubUrl = \App\Filament\Pages\SupportHub::getUrl();
    $listUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
    $preview = $this->customerPreview;
    $live = $preview['live'] ?? [];
@endphp

{!! \App\Support\SupportStyles::html() !!}
{!! \App\Support\SupportStyles::navigatedScript() !!}

<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'isp-support-ticket-create',
    ])
    data-ticket-create-component="{{ $this->getId() }}"
>
    <div class="sp-pro sp-create-pro">
        <header class="sp-ticket-hero sp-create-hero">
            <div class="sp-create-hero__row">
                <div>
                    <p class="sp-create-hero__eyebrow">Support · New ticket</p>
                    <h1 class="sp-create-hero__title">Open a service desk ticket</h1>
                    <p class="sp-create-hero__sub">Link subscriber, check PPP/ONU, assign technician, save to queue.</p>
                </div>
                <div class="sp-create-hero__links">
                    <a href="{{ $listUrl }}" class="sp-360__link">← Queue</a>
                    <a href="{{ $hubUrl }}" class="sp-360__link">Center</a>
                </div>
            </div>
            <ol class="sp-create-steps" aria-label="Create ticket steps">
                <li @class(['sp-create-steps__item', 'sp-create-steps__item--done' => $this->selectedSubscriber])>1. Find subscriber</li>
                <li @class(['sp-create-steps__item', 'sp-create-steps__item--done' => $this->selectedSubscriber])>2. Review status</li>
                <li @class(['sp-create-steps__item', 'sp-create-steps__item--done' => filled($this->data['assigned_to'] ?? null)])>3. Assign &amp; route</li>
                <li class="sp-create-steps__item">4. Save ticket</li>
            </ol>
        </header>

        <div class="sp-create-layout">
            <aside class="sp-create-layout__rail" aria-label="Subscriber lookup">
                <div wire:key="support-subscriber-search-{{ $this->getId() }}">
                    @include('filament.resources.support-ticket-resource.partials.subscriber-search')
                </div>

                @if (! empty($preview['linked']))
                    @include('filament.resources.support-ticket-resource.partials.customer-preview', ['preview' => $preview, 'live' => $live])
                @endif
            </aside>

            <div class="sp-create-layout__main">
                <x-filament-panels::form
                    id="form"
                    :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                    wire:submit="createTicket"
                >
                    {{ $this->form }}

                    <div class="sp-create-form-footer">
                        @unless ($this->canSaveTicket())
                            <p class="sp-create-form-footer__warn">
                                Type username (ID) and press Enter — e.g. habib3.kp (0603).
                            </p>
                        @endunless
                        @if (blank($this->data['description'] ?? null))
                            <p class="sp-create-form-footer__warn">
                                Problem details (description) is required before saving.
                            </p>
                        @endif
                        <x-filament-panels::form.actions
                            :actions="$this->getCachedFormActions()"
                            :full-width="$this->hasFullWidthFormActions()"
                        />
                    </div>
                </x-filament-panels::form>
            </div>
        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>
