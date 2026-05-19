<x-filament-panels::page class="isp-reports-page">
    <div class="space-y-5">
        <section class="isp-reports-hero">
            <div class="isp-reports-hero__main">
                <p class="isp-reports-hero__eyebrow">Reports</p>
                <h2 class="isp-reports-hero__title">Print Reports</h2>
                <p class="isp-reports-hero__sub">Open a print-friendly view and save as PDF from your browser.</p>
            </div>
        </section>

        <div class="isp-reports-print-grid">
            @foreach ($this->printables as $item)
                <a href="{{ $item['url'] }}" target="_blank" rel="noopener" class="isp-reports-print-card">
                    <x-filament::icon :icon="$item['icon']" class="h-6 w-6" />
                    <span class="isp-reports-print-card__title">{{ $item['label'] }}</span>
                    <span class="isp-reports-print-card__hint">{{ $item['hint'] }}</span>
                </a>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
