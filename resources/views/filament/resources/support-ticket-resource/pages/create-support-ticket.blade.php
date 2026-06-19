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
                                        wire:key="sp-cat-{{ $item['key'] }}"
                                        wire:loading.attr="disabled"
                                        wire:target="pickCategory"
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
                        id="support-ticket-create-form"
                        wire:submit="create"
                    >
                        {{ $this->form }}

                        <div class="sp-create-form-footer">
                            @unless ($this->canSaveTicket())
                                <p class="sp-create-form-footer__warn">
                                    Link subscriber first — search above, tap a result, then fill complaint details.
                                </p>
                            @endunless

                            <button
                                type="submit"
                                class="sp-create-submit-btn fi-btn fi-btn-size-lg fi-btn-color-primary"
                                wire:loading.attr="disabled"
                                wire:target="create"
                            >
                                <span wire:loading.remove wire:target="create">Create ticket → Open</span>
                                <span wire:loading wire:target="create">Creating ticket…</span>
                            </button>
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
    body.isp-support-create-v4 .fi-main-ctn,
    body.isp-support-ticket-create .fi-main-ctn {
        padding-left: 0.65rem !important;
        padding-right: 0.65rem !important;
        padding-bottom: calc(var(--isp-mobile-bar-height, 10.5rem) + 1rem + env(safe-area-inset-bottom, 0px)) !important;
    }

    .sp-create-layout--v4 { display: flex; flex-direction: column; gap: 0.85rem; }

    .sp-create-form-footer {
        position: relative;
        z-index: 1;
        margin-top: 1rem;
        padding-top: 0.75rem;
        border-top: 1px solid var(--sp-border, #e2e8f0);
        background: var(--sp-card, #fff);
    }

    .sp-create-submit-btn {
        width: 100% !important;
        min-height: 3.1rem !important;
        font-size: 0.95rem !important;
        font-weight: 800 !important;
        border-radius: 10px !important;
        background: linear-gradient(135deg, #d97706, #f59e0b) !important;
        border: 0 !important;
        color: #fff !important;
    }

    .sp-create-submit-btn:disabled {
        opacity: 0.45 !important;
        cursor: not-allowed !important;
    }

    .sp-create-categories,
    .sp-create-cat-pill {
        position: relative;
        z-index: 2;
    }

    .sp-create-cat-pill {
        min-height: 2.5rem;
        touch-action: manipulation;
    }

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
