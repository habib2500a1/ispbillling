@php
    $hubUrl = \App\Filament\Pages\SupportHub::getUrl();
    $listUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
    $preview = $this->customerPreview;
    $live = $preview['live'] ?? [];
    $ticketCreateJs = public_path('js/support-ticket-create.js');
    $ticketCreateJsVer = file_exists($ticketCreateJs) ? (int) filemtime($ticketCreateJs) : 1;
    $selectedIssue = $this->data['issue_type'] ?? null;
    $categories = collect($this->getCategoryPickerItems())->groupBy('group');
    $aiSuggestions = $this->createAiSuggestions;
@endphp

{!! \App\Support\SupportStyles::html() !!}
{!! \App\Support\SupportStyles::navigatedScript() !!}
<script src="{{ asset('js/support-ticket-create.js') }}?v={{ $ticketCreateJsVer }}" defer data-cfasync="false"></script>

<x-filament-panels::page
    @class([
        'fi-resource-create-record-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
        'isp-support-ticket-create',
    ])
>
    <div class="sp-pro sp-create-pro sp-create-enterprise">
        <header class="sp-create-hero sp-create-hero--enterprise">
            <div class="sp-create-hero__row">
                <div>
                    <p class="sp-create-hero__eyebrow">NOC · New service ticket</p>
                    <h1 class="sp-create-hero__title">Create ticket</h1>
                    <p class="sp-create-hero__sub">Link subscriber · pick category · AI network hints · assign field tech · SLA auto-set</p>
                </div>
                <div class="sp-create-hero__links">
                    <a href="{{ $listUrl }}" class="sp-360__link" data-navigate="false">← Queue</a>
                    <a href="{{ $hubUrl }}" class="sp-360__link" data-navigate="false">NOC Center</a>
                </div>
            </div>

            <div class="sp-create-kpi-strip">
                <div class="sp-create-kpi">
                    <span class="sp-create-kpi__label">Priority</span>
                    <strong class="sp-create-kpi__value">{{ $this->priorityCodeLabel() }}</strong>
                </div>
                <div class="sp-create-kpi">
                    <span class="sp-create-kpi__label">SLA target</span>
                    <strong class="sp-create-kpi__value sp-create-kpi__value--sm">{{ $this->slaPreviewLabel() }}</strong>
                </div>
                <div class="sp-create-kpi">
                    <span class="sp-create-kpi__label">Subscriber</span>
                    <strong class="sp-create-kpi__value sp-create-kpi__value--sm">
                        @if ($this->selectedSubscriber)
                            {{ $this->selectedSubscriber['customer_code'] ?? 'Linked' }}
                        @else
                            Not linked
                        @endif
                    </strong>
                </div>
            </div>

            <ol class="sp-create-steps" aria-label="Create ticket steps">
                <li @class(['sp-create-steps__item', 'sp-create-steps__item--done' => $this->selectedSubscriber])>1. Find subscriber</li>
                <li @class(['sp-create-steps__item', 'sp-create-steps__item--done' => $this->selectedSubscriber])>2. Network / ONU check</li>
                <li @class(['sp-create-steps__item', 'sp-create-steps__item--done' => filled($selectedIssue)])>3. Category &amp; priority</li>
                <li @class(['sp-create-steps__item', 'sp-create-steps__item--done' => filled($this->data['assigned_to'] ?? null)])>4. Assign &amp; save</li>
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
                @if ($aiSuggestions !== [])
                    <section class="sp-ai-panel sp-create-ai" aria-label="AI ticket hints">
                        <h2 class="sp-ai-panel__title">AI analysis (live network)</h2>
                        <ul class="sp-ai-panel__list">
                            @foreach ($aiSuggestions as $suggestion)
                                <li @class(['sp-ai-panel__item', 'sp-ai-panel__item--'.$suggestion['tone']])>
                                    <strong>{{ $suggestion['label'] }}</strong>
                                    <span>{{ $suggestion['detail'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endif

                <section class="sp-create-categories" aria-label="Quick category pick">
                    <h2 class="sp-create-categories__title">Ticket category</h2>
                    <p class="sp-create-categories__hint">Tap a category — priority and department auto-set from NOC rules.</p>
                    @foreach ($categories as $group => $items)
                        <div class="sp-create-cat-group">
                            <span class="sp-create-cat-group__label">{{ $group }}</span>
                            <div class="sp-create-cat-group__pills">
                                @foreach ($items as $item)
                                    <button
                                        type="button"
                                        wire:click="pickCategory('{{ $item['key'] }}')"
                                        wire:loading.attr="disabled"
                                        @class([
                                            'sp-create-cat-pill',
                                            'sp-create-cat-pill--active' => $selectedIssue === $item['key'],
                                            'sp-create-cat-pill--'.$item['default_priority'],
                                        ])
                                    >
                                        {{ $item['label'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </section>

                <x-filament-panels::form
                    id="form"
                    :wire:key="$this->getId() . '.forms.' . $this->getFormStatePath()"
                    wire:submit="createTicket"
                >
                    {{ $this->form }}

                    <div class="sp-create-form-footer">
                        @unless ($this->canSaveTicket())
                            <p class="sp-create-form-footer__warn">
                                Link a subscriber on the left before creating the ticket.
                            </p>
                        @endunless
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
