@php
    $statCards = $this->getStatCards();
    $tabs = $this->getPresetTabs();
    $indexUrl = \App\Filament\Resources\CustomerResource::getUrl('index');
    $createUrl = \App\Filament\Resources\CustomerResource::getUrl('create');
    $canExport = \App\Filament\Pages\ExportClientsReport::canAccess();
    $exportUrl = $canExport ? \App\Filament\Pages\ExportClientsReport::getUrl() : null;
    $clDirCssV = @filemtime(public_path('css/clients-directory-pro.css')) ?: time();
@endphp

<link rel="stylesheet" href="{{ asset('css/clients-directory-pro.css') }}?v={{ $clDirCssV }}" data-clients-directory="1" id="clients-directory-pro-css">

<script data-cfasync="false">
(function () {
    var id = 'clients-directory-pro-css';
    var href = @json(asset('css/clients-directory-pro.css').'?v='.$clDirCssV);
    var existing = document.getElementById(id);
    if (existing && existing.getAttribute('href') === href) return;
    if (existing) existing.remove();
    var link = document.createElement('link');
    link.id = id;
    link.rel = 'stylesheet';
    link.href = href;
    link.setAttribute('data-clients-directory', '1');
    document.head.appendChild(link);
})();
</script>

<x-filament-panels::page class="isp-clients-page">
    <div class="cl-dir" wire:key="clients-directory-{{ $this->getId() }}">
        <div class="cl-dir-actions no-print">
            <nav class="cl-dir-tabs" aria-label="Quick presets">
                @foreach ($tabs as $tab)
                    <a
                        href="{{ $indexUrl }}?preset={{ $tab['key'] }}"
                        @class(['cl-dir-tab', 'cl-dir-tab--active' => $preset === $tab['key']])
                    >{{ $tab['label'] }} <span class="cl-dir-tab__count">{{ number_format($tab['count']) }}</span></a>
                @endforeach
            </nav>
            <div class="cl-dir-actions__right">
                @foreach ($this->getCachedHeaderActions() as $action)
                    {{ $action }}
                @endforeach
                <a href="{{ $createUrl }}" class="cl-dir-btn cl-dir-btn--primary">
                    <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                    Add Client
                </a>
            </div>
        </div>

        <div class="cl-dir-stats">
            @foreach ($statCards as $card)
                <article @class(['cl-dir-stat', 'cl-dir-stat--'.$card['tone']])>
                    <div class="cl-dir-stat__body">
                        <span class="cl-dir-stat__label">{{ $card['label'] }}</span>
                        <strong class="cl-dir-stat__value">{{ $card['value'] }}</strong>
                        @if (! empty($card['hint']))
                            <span class="cl-dir-stat__hint">{{ $card['hint'] }}</span>
                        @endif
                    </div>
                    <span class="cl-dir-stat__icon" aria-hidden="true">
                        <x-filament::icon :icon="$card['icon']" class="h-5 w-5" />
                    </span>
                </article>
            @endforeach
        </div>

        <section class="cl-dir-table">
            @if ($canExport && $exportUrl)
                <div class="cl-dir-table-export no-print">
                    <a href="{{ $exportUrl }}" class="cl-dir-btn cl-dir-btn--ghost cl-dir-btn--sm">
                        <x-filament::icon icon="heroicon-m-arrow-down-tray" class="h-4 w-4" />
                        Export
                    </a>
                </div>
            @endif
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
