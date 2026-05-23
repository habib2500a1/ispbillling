<x-filament-panels::page class="isp-reseller-page">
    <div class="space-y-5">
        <section class="isp-reseller-hero isp-reseller-hero--compact">
            <div class="isp-reseller-hero__main">
                <p class="isp-reseller-hero__eyebrow">Resellers</p>
                <h2 class="isp-reseller-hero__title">Reseller packages & selling price</h2>
                <p class="isp-reseller-hero__sub">
                    Which package each reseller can sell and at what price. Assign packages on each reseller’s
                    <strong>Packages &amp; selling price</strong> tab, or use the overview below.
                </p>
            </div>
        </section>

        <section class="isp-reseller-table-card">
            {{ $this->table }}
        </section>
    </div>
</x-filament-panels::page>
