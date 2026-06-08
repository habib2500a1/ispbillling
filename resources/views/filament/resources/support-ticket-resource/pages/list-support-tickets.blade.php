@php
    $chips = $this->getSupportQueueChips();
    $stats = $this->getSupportListStats();
    $hubUrl = \App\Filament\Pages\SupportHub::getUrl();
    $createUrl = \App\Filament\Resources\SupportTicketResource::getUrl('create');
    $dockLinks = $this->getSupportDockLinks();
@endphp

{!! \App\Support\SupportStyles::navigatedScript() !!}
<script src="{{ asset('js/support-tickets-v3.js') }}?v={{ @filemtime(public_path('js/support-tickets-v3.js')) ?: 1 }}" defer></script>

<x-filament-panels::page class="isp-support-tickets-page">
    <div class="sp-pro" data-view="table" wire:loading.class="opacity-70">
        <header class="sp-inv-hero">
            <div>
                <span style="font-size:0.65rem;font-weight:800;text-transform:uppercase;letter-spacing:0.06em;color:var(--sp-amber);">Ticket center</span>
                <h1 class="sp-inv-hero__title">All tickets</h1>
                <p class="sp-inv-hero__sub">Queue · SLA · bulk assign · live chat — enterprise service desk view</p>
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:0.35rem;">
                <a href="{{ $hubUrl }}" style="display:inline-flex;align-items:center;gap:0.35rem;min-height:2.25rem;padding:0.35rem 0.65rem;border-radius:8px;border:1px solid var(--sp-border);font-size:0.72rem;font-weight:700;text-decoration:none;color:var(--sp-text);">
                    <x-filament::icon icon="heroicon-m-lifebuoy" class="h-4 w-4" />
                    Center
                </a>
                <a href="{{ $createUrl }}" style="display:inline-flex;align-items:center;gap:0.35rem;min-height:2.25rem;padding:0.35rem 0.65rem;border-radius:8px;background:var(--sp-amber);font-size:0.72rem;font-weight:700;text-decoration:none;color:#fff;">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                    New ticket
                </a>
            </div>
        </header>

        <div class="sh-stats" style="margin-bottom:0.65rem;">
            @foreach ($stats as $stat)
                <div class="sh-stat" style="cursor:default;">
                    <span class="sh-stat__label">{{ $stat['label'] }}</span>
                    <strong class="sh-stat__value">{{ $stat['value'] }}</strong>
                    @if (! empty($stat['hint']))
                        <span class="sh-stat__hint">{{ $stat['hint'] }}</span>
                    @endif
                </div>
            @endforeach
        </div>

        <nav class="sp-queue-chips" aria-label="Ticket queues">
            @foreach ($chips as $chip)
                <a href="{{ $chip['url'] }}" @class(['sp-queue-chip', 'sp-queue-chip--active' => $chip['active']])>
                    {{ $chip['label'] }}
                    @if ($chip['count'] !== null)
                        <span class="sp-queue-chip__count">{{ number_format($chip['count']) }}</span>
                    @endif
                </a>
            @endforeach
        </nav>

        <section class="sp-inv-toolbar">
            <div class="sp-inv-toolbar__row" style="justify-content:space-between;">
                <p style="margin:0;font-size:0.75rem;font-weight:600;color:var(--sp-muted);">
                    Use table filters for department, priority, and status
                </p>
                <div class="sp-view-toggle" aria-label="View mode">
                    <button type="button" class="sp-view-toggle__btn sp-view-toggle__btn--active" data-sp-view="table" title="Table">
                        <x-filament::icon icon="heroicon-m-table-cells" class="h-4 w-4" />
                    </button>
                    <button type="button" class="sp-view-toggle__btn" data-sp-view="cards" title="Cards">
                        <x-filament::icon icon="heroicon-m-squares-2x2" class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>

        <div class="sp-ticket-mobile-cards" data-sp-mobile-cards></div>

        {{ $this->table }}

        <nav class="sh-dock" aria-label="Support quick nav">
            <div class="sh-dock__inner">
                @foreach ($dockLinks as $link)
                    @if (filled($link['url'] ?? null))
                        <a href="{{ $link['url'] }}" @class(['sh-dock__link', 'sh-dock__link--active' => ! empty($link['active'])])>
                            <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            <span>{{ $link['label'] }}</span>
                        </a>
                    @endif
                @endforeach
            </div>
        </nav>
    </div>
</x-filament-panels::page>
