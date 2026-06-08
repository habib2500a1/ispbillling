<x-filament-panels::page>
    <div class="isp-bi-page">
        <x-isp.reports.page-header
            eyebrow="Export center"
            title="Print &amp; export reports"
            subtitle="Open a print-friendly view and save as PDF from your browser. All existing export paths preserved."
        />

        <div class="isp-bi-toolbar">
            <a href="{{ \App\Filament\Pages\ReportsHub::getUrl() }}" class="isp-bi-back">← Intelligence center</a>
            <a href="{{ \App\Filament\Pages\PaymentsReport::getUrl() }}" class="isp-bi-back">CSV payments →</a>
        </div>

        <section class="isp-bi-section">
            <div class="isp-bi-section__head">
                <div>
                    <h2 class="isp-bi-section__title">Print-ready reports</h2>
                    <p class="isp-bi-section__desc">{{ count($this->printables) }} reports · opens in new tab for PDF save</p>
                </div>
            </div>
            <div class="isp-bi-section__body">
                <div class="isp-bi-catalog">
                    @foreach ($this->printables as $item)
                        <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="isp-bi-catalog-card">
                            <span class="isp-bi-catalog-card__icon">
                                <x-filament::icon :icon="$item['icon']" class="h-5 w-5" />
                            </span>
                            <div class="min-w-0">
                                <p class="isp-bi-catalog-card__eyebrow">Print / PDF</p>
                                <p class="isp-bi-catalog-card__title">{{ $item['label'] }}</p>
                                <p class="isp-bi-catalog-card__desc">{{ $item['hint'] }}</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="isp-bi-section">
            <div class="isp-bi-section__head">
                <div>
                    <h2 class="isp-bi-section__title">CSV exports</h2>
                    <p class="isp-bi-section__desc">Direct spreadsheet downloads</p>
                </div>
            </div>
            <div class="isp-bi-section__body">
                <div class="isp-bi-export-dock">
                    <a href="{{ \App\Filament\Pages\PaymentsReport::getUrl() }}" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-banknotes" class="h-5 w-5" />
                        Payments CSV
                    </a>
                    <a href="{{ \App\Filament\Pages\ExportClientsReport::getUrl() }}" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-5 w-5" />
                        Clients CSV
                    </a>
                    <a href="{{ \App\Filament\Pages\AreaWiseClientsReport::getUrl() }}" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-map-pin" class="h-5 w-5" />
                        Area CSV
                    </a>
                    <a href="{{ \App\Filament\Pages\BtrcReport::getUrl() }}" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-5 w-5" />
                        BTRC DIS
                    </a>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
