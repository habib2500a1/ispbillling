@php
    $hubUrl = \App\Filament\Pages\SupportHub::getUrl();
    $listUrl = \App\Filament\Resources\SupportTicketResource::getUrl('index');
    $preview = $this->customerPreview;
    $live = $preview['live'] ?? [];
    $ticketCreateJs = public_path('js/support-ticket-create.js');
    $ticketCreateJsVer = file_exists($ticketCreateJs) ? (int) filemtime($ticketCreateJs) : time();
    $cssVer = \App\Support\SupportStyles::version();
    $selectedIssue = $this->data['issue_type'] ?? null;
    $categories = collect($this->getCategoryPickerItems())->groupBy('group');
    $aiSuggestions = $this->createAiSuggestions;
    $hasSubscriber = $this->selectedSubscriber !== null;
    $hasAssignee = filled($this->data['assigned_to'] ?? null);
    $hasCategory = filled($selectedIssue);
@endphp

{!! \App\Support\SupportStyles::html() !!}
{!! \App\Support\SupportStyles::navigatedScript() !!}
<script src="{{ asset('js/support-ticket-create.js') }}?v={{ $ticketCreateJsVer }}" defer data-cfasync="false"></script>

<x-filament-panels::page class="isp-support-create-page fi-resource-create-record-page">
    <div class="sp-pro sp-create-pro" data-sp-create-v4>
        <header class="sh-hero sp-grad--hero sp-create-noc-hero">
            <div>
                <span class="sh-hero__badge">Enterprise NOC · Ticket create</span>
                <h1 class="sh-hero__title">New service ticket</h1>
                <p class="sh-hero__sub">Search subscriber · live ONU · category · assign tech · save to queue</p>
                <span class="sp-create-version-badge">UI v4 · mobile ready</span>
                <div class="sh-hero__actions">
                    <a href="{{ $listUrl }}" class="sh-btn sh-btn--white" data-navigate="false">← Ticket queue</a>
                    <a href="{{ $hubUrl }}" class="sh-btn sh-btn--glass" data-navigate="false">NOC center</a>
                    @if ($hasSubscriber)
                        <button type="button" wire:click="assignTicketToMe" class="sh-btn sh-btn--glass">Assign to me</button>
                    @endif
                </div>
            </div>
            <div class="sh-hero__kpi">
                <span class="sh-hero__kpi-label">Priority / SLA</span>
                <strong class="sh-hero__kpi-value" style="font-size:1.1rem;">{{ $this->priorityCodeLabel() }}</strong>
                <span class="sh-hero__kpi-label" style="margin-top:0.35rem;display:block;font-size:0.65rem;line-height:1.35;">
                    {{ $this->slaPreviewLabel() }}
                </span>
                <span class="sh-hero__kpi-label" style="margin-top:0.5rem;display:block;">
                    Subscriber: {{ $this->selectedSubscriber['customer_code'] ?? '— not linked —' }}
                </span>
            </div>
        </header>

        <ol class="sp-create-steps--mobile" aria-label="Create ticket progress">
            <li @class(['is-done' => $hasSubscriber])>1 · Subscriber</li>
            <li @class(['is-done' => $hasSubscriber && ! empty($preview['linked'])])>2 · ONU</li>
            <li @class(['is-done' => $hasCategory || $hasAssignee])>3 · Category</li>
            <li>4 · Save</li>
        </ol>

        <div class="sp-create-layout sp-create-layout--v4">
            <aside class="sp-create-layout__rail">
                <div class="sp-create-card">
                    @include('filament.resources.support-ticket-resource.partials.subscriber-search')
                </div>

                @if (! empty($preview['linked']))
                    <div class="sp-create-card sp-create-card--onu">
                        @include('filament.resources.support-ticket-resource.partials.customer-preview', ['preview' => $preview, 'live' => $live])
                    </div>
                @endif
            </aside>

            <div class="sp-create-layout__main">
                @if ($aiSuggestions !== [])
                    <section class="sp-ai-panel sp-create-ai sp-create-card">
                        <h2 class="sp-ai-panel__title">⚡ AI network analysis</h2>
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

                <section class="sp-create-card sp-create-categories">
                    <h2 class="sp-create-categories__title">Complaint category</h2>
                    <p class="sp-create-categories__hint">Tap — auto-sets P1–P4 priority &amp; department</p>
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

                <div class="sp-create-card sp-create-form-card">
                    <h2 class="sp-create-form-card__title">Ticket details</h2>
                    <x-filament-panels::form
                        id="form"
                        wire:submit="createTicket"
                    >
                        {{ $this->form }}

                        <div class="sp-create-form-footer">
                            @unless ($this->canSaveTicket())
                                <p class="sp-create-form-footer__warn">
                                    Link subscriber first (search above)
                                </p>
                            @endunless
                            <x-filament-panels::form.actions
                                :actions="$this->getCachedFormActions()"
                                :full-width="false"
                            />
                        </div>
                    </x-filament-panels::form>
                </div>
            </div>
        </div>
    </div>

    <x-filament-panels::page.unsaved-data-changes-alert />
</x-filament-panels::page>

<style>
    /* Critical — works even if CSS bundle is cached */
    body.isp-support-create-v4 .fi-header,
    body.isp-support-create-v4 .fi-page-header,
    .isp-support-create-page .fi-header,
    .isp-support-create-page .fi-page-header { display: none !important; }
    body.isp-support-create-v4 .fi-main,
    .isp-support-create-page .fi-main { background: var(--sp-surface, #f8fafc) !important; }
    .sp-create-noc-hero.sp-grad--hero {
        background: linear-gradient(135deg, #b45309 0%, #d97706 45%, #f59e0b 100%) !important;
        color: #fff !important;
    }
    @media (max-width: 767px) {
        .sp-create-layout--v4 { display: flex; flex-direction: column; gap: 0.85rem; }
        .sp-create-form-footer { position: sticky; bottom: 0; z-index: 40; background: var(--sp-card, #fff); }
        .sp-create-form-footer .fi-btn { width: 100% !important; min-height: 3rem !important; }
        .isp-collection-search-input { font-size: 16px !important; min-height: 2.85rem; }
    }
</style>

<script data-cfasync="false">
(function () {
    function ensureCreateV4() {
        if (window.location.pathname.indexOf('support-tickets/create') === -1) {
            return;
        }
        if (!document.querySelector('[data-sp-create-v4]')) {
            window.location.reload();
        }
    }
    document.addEventListener('livewire:navigated', ensureCreateV4);
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', ensureCreateV4);
    } else {
        ensureCreateV4();
    }
})();
</script>
