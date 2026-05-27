@php
    $links = [
        ['eyebrow' => 'Desk', 'label' => 'Bill collection', 'hint' => 'Search & pay', 'url' => \App\Filament\Pages\BillCollectionDesk::getUrl(), 'icon' => 'heroicon-o-currency-dollar'],
        ['eyebrow' => 'Trail', 'label' => 'Bill money trail', 'hint' => 'Where cash went · costs', 'url' => \App\Filament\Pages\BillingFundFlowReport::getUrl(), 'icon' => 'heroicon-o-arrows-right-left'],
        ['eyebrow' => 'Hub', 'label' => 'Billing overview', 'hint' => 'Modules & reports', 'url' => \App\Filament\Pages\BillingOverview::getUrl(), 'icon' => 'heroicon-o-receipt-percent'],
        ['eyebrow' => 'Invoices', 'label' => 'All invoices', 'hint' => 'Open & partial', 'url' => \App\Filament\Resources\InvoiceResource::getUrl('index'), 'icon' => 'heroicon-o-document-text'],
        ['eyebrow' => 'Payments', 'label' => 'Payments', 'hint' => 'Gateway & manual', 'url' => \App\Filament\Resources\PaymentResource::getUrl('index'), 'icon' => 'heroicon-o-credit-card'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            eyebrow="Revenue operations"
            title="Billing dashboard"
            description="Revenue, collections, dues and invoice health — refreshed live for billing & accounts teams."
            class="isp-hub-hero--emerald"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <section class="isp-hub-section">
            <div class="isp-hub-section__head">
                <div>
                    <h2 class="isp-hub-section__title">Billing workflows</h2>
                    <p class="isp-hub-section__desc">Open revenue desks, invoice queues, and money trail tools without leaving the billing command view.</p>
                </div>
                <span class="isp-hub-section__meta">Collections live</span>
            </div>
            <div class="isp-hub-link-grid isp-hub-link-grid--2 isp-hub-link-grid--4">
                @foreach ($links as $link)
                    <a href="{{ $link['url'] }}" class="isp-module-card group">
                        <div class="flex items-start gap-3">
                            <span class="isp-module-icon text-emerald-600">
                                <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <p class="isp-module-card__eyebrow">{{ $link['eyebrow'] }}</p>
                                <p class="isp-module-card__title">{{ $link['label'] }}</p>
                                <p class="isp-module-card__desc">{{ $link['hint'] }}</p>
                            </div>
                            <span class="isp-module-card__arrow" aria-hidden="true">→</span>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columns="['default' => 1, 'lg' => 2]"
        />
    </div>
</x-filament-panels::page>
