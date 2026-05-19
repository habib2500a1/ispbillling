@php
    $links = [
        ['eyebrow' => 'Desk', 'label' => 'Bill collection', 'hint' => 'Search & pay', 'url' => \App\Filament\Pages\BillCollectionDesk::getUrl(), 'icon' => 'heroicon-o-currency-dollar'],
        ['eyebrow' => 'Hub', 'label' => 'Billing overview', 'hint' => 'Modules & reports', 'url' => \App\Filament\Pages\BillingOverview::getUrl(), 'icon' => 'heroicon-o-receipt-percent'],
        ['eyebrow' => 'Invoices', 'label' => 'All invoices', 'hint' => 'Open & partial', 'url' => \App\Filament\Resources\InvoiceResource::getUrl('index'), 'icon' => 'heroicon-o-document-text'],
        ['eyebrow' => 'Payments', 'label' => 'Payments', 'hint' => 'Gateway & manual', 'url' => \App\Filament\Resources\PaymentResource::getUrl('index'), 'icon' => 'heroicon-o-credit-card'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            title="Billing dashboard"
            description="Revenue, collections, dues and invoice health — refreshed live for billing & accounts teams."
            class="isp-hub-hero--emerald"
        />

        <x-isp.hub-stat-grid :stats="$this->getStatCards()" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($links as $link)
                <a href="{{ $link['url'] }}" class="isp-module-card group">
                    <div class="flex items-start gap-3">
                        <span class="isp-module-icon text-emerald-600">
                            <x-filament::icon :icon="$link['icon']" class="h-5 w-5" />
                        </span>
                        <div class="min-w-0 flex-1">
                            <p class="text-xs font-bold uppercase text-gray-500 dark:text-gray-400">{{ $link['eyebrow'] }}</p>
                            <p class="mt-0.5 font-semibold text-gray-900 dark:text-white">{{ $link['label'] }}</p>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $link['hint'] }}</p>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <x-filament-widgets::widgets
            :widgets="$this->getFooterWidgets()"
            :columns="['default' => 1, 'lg' => 2]"
        />
    </div>
</x-filament-panels::page>
