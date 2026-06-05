<x-filament-panels::page class="isp-reports-page">
    <div class="space-y-5">
        <section class="isp-reports-hero">
            <div class="isp-reports-hero__main">
                <p class="isp-reports-hero__eyebrow">Inventory Pro</p>
                <h2 class="isp-reports-hero__title">{{ $this->getReportTitle() }}</h2>
                @if (filled($this->getReportSubtitle()))
                    <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">{{ $this->getReportSubtitle() }}</p>
                @endif
            </div>
            @if (method_exists($this, 'getReportFiltersView'))
                {{ $this->getReportFiltersView() }}
            @endif
        </section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
