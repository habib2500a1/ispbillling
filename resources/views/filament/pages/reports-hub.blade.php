@php
    $dash = $this->getDashboard();
    $summary = $dash['summary'];
    $intel = $dash['intel'];
    $ai = $dash['ai'];
    $health = (int) ($intel['network_health_score'] ?? 0);
    $catalog = $this->getReportCatalog();
    $catalogJson = json_encode($catalog, JSON_THROW_ON_ERROR);
@endphp

<x-filament-panels::page>
    <div class="isp-bi-page" x-data="ispBiHub({{ $catalogJson }})">
        <section class="isp-bi-hero">
            <div>
                <p class="isp-bi-hero__eyebrow">Enterprise ISP Intelligence</p>
                <h1 class="isp-bi-hero__title">Reports &amp; Analytics Center</h1>
                <p class="isp-bi-hero__sub">
                    Collection report · revenue · due · customers · network · GIS · tickets — unified business intelligence.
                    All existing reports and calculations preserved.
                </p>
            </div>
            <div class="isp-bi-hero__score">
                <span>Network health</span>
                <strong>{{ $health }}%</strong>
            </div>
        </section>

        <div class="isp-bi-toolbar">
            <x-isp.reports.search />
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl() }}" class="isp-bi-back">
                Open analytics dashboard →
            </a>
        </div>

        <div class="isp-bi-mobile-summary">
            <x-isp.reports.kpi-card label="Today" :value="number_format($intel['revenue_today'] ?? 0, 0).' BDT'" tone="emerald" />
            <x-isp.reports.kpi-card label="MTD collected" :value="number_format($summary['collected'], 0).' BDT'" tone="violet" />
            <x-isp.reports.kpi-card label="Due" :value="number_format($dash['due_total'], 0).' BDT'" tone="rose" />
            <x-isp.reports.kpi-card label="Online" :value="number_format($intel['customers_online'] ?? 0)" tone="sky" />
        </div>

        <section>
            <div class="isp-bi-kpi-grid isp-bi-kpi-grid--6">
                <x-isp.reports.kpi-card
                    label="Total revenue (MTD)"
                    :value="number_format($summary['invoiced'], 2).' BDT'"
                    :hint="'Collected '.number_format($summary['collected'], 2).' BDT'"
                    tone="violet"
                    :href="\App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'revenue'])"
                    :delay="0"
                />
                <x-isp.reports.kpi-card
                    label="Today's collection"
                    :value="number_format($intel['revenue_today'] ?? 0, 2).' BDT'"
                    hint="Live from operations snapshot"
                    tone="emerald"
                    :href="\App\Filament\Pages\CollectionDeskReport::getUrl()"
                    :delay="40"
                />
                <x-isp.reports.kpi-card
                    label="Monthly collection"
                    :value="number_format($summary['collected'], 2).' BDT'"
                    :hint="$summary['collection_rate'].'% collection rate'"
                    tone="emerald"
                    :href="\App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'collection'])"
                    :delay="80"
                />
                <x-isp.reports.kpi-card
                    label="Due amount"
                    :value="number_format($dash['due_total'], 2).' BDT'"
                    hint="Open invoice balances"
                    tone="amber"
                    :href="\App\Filament\Pages\DueReportProPage::getUrl()"
                    :delay="120"
                />
                <x-isp.reports.kpi-card
                    label="Overdue amount"
                    :value="number_format($dash['overdue'], 2).' BDT'"
                    hint="Past due aging buckets"
                    tone="rose"
                    :href="\App\Filament\Pages\DueReportProPage::getUrl()"
                    :delay="160"
                />
                <x-isp.reports.kpi-card
                    label="Revenue forecast"
                    :value="number_format($ai['revenue_forecast_mtd'] ?? 0, 0).' BDT'"
                    :hint="(($ai['revenue_trend_pct'] ?? 0) >= 0 ? '+' : '').($ai['revenue_trend_pct'] ?? 0).'% vs last month'"
                    tone="sky"
                    :href="\App\Filament\Pages\AiAnalyticsDashboard::getUrl()"
                    :delay="200"
                />
            </div>
        </section>

        <section>
            <div class="isp-bi-kpi-grid isp-bi-kpi-grid--6">
                <x-isp.reports.kpi-card
                    label="Active customers"
                    :value="number_format($intel['customers_total'] ?? $summary['active_subscribers'])"
                    :hint="number_format($intel['customers_online'] ?? $summary['online_now']).' online now'"
                    tone="sky"
                    :href="\App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'growth'])"
                />
                <x-isp.reports.kpi-card
                    label="New registrations"
                    :value="'+'.$summary['new_subscribers']"
                    :hint="'Churned −'.$summary['churned'].' this period'"
                    tone="emerald"
                    :href="\App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'churn'])"
                />
                <x-isp.reports.kpi-card
                    label="Online ONUs"
                    :value="number_format($intel['onus_active'] ?? 0).'/'.number_format(($intel['onus_active'] ?? 0) + ($intel['onus_offline'] ?? 0))"
                    :hint="number_format($intel['onus_offline'] ?? 0).' offline'"
                    tone="violet"
                    :href="\App\Filament\Pages\OltHub::getUrl()"
                />
                <x-isp.reports.kpi-card
                    label="Online routers"
                    :value="number_format($intel['routers_online'] ?? 0).'/'.number_format($intel['routers_total'] ?? 0)"
                    hint="MikroTik API status"
                    tone="sky"
                    :href="\App\Filament\Pages\NetworkIntelligenceHub::getUrl()"
                />
                <x-isp.reports.kpi-card
                    label="Open tickets"
                    :value="number_format($intel['open_tickets'] ?? 0)"
                    :hint="number_format($intel['active_faults'] ?? 0).' active faults'"
                    tone="amber"
                    :href="\App\Filament\Pages\SupportHub::getUrl()"
                />
                <x-isp.reports.kpi-card
                    label="OLTs online"
                    :value="number_format($intel['olts_online'] ?? 0).'/'.number_format($intel['olts_total'] ?? 0)"
                    hint="GPON infrastructure"
                    tone="violet"
                    :href="\App\Filament\Pages\OpticalMonitoringHub::getUrl()"
                />
            </div>
        </section>

        <nav class="isp-bi-domain-nav" aria-label="Analytics domains">
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'revenue']) }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-chart-bar" />
                Revenue analytics
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'collection']) }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-banknotes" />
                Collection analytics
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'growth']) }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-user-group" />
                Customer analytics
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'online']) }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-signal" />
                Network analytics
            </a>
            <a href="{{ \App\Filament\Pages\AnalyticsReports::getUrl(['tab' => 'area']) }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-map" />
                GIS &amp; area analytics
            </a>
            <a href="{{ \App\Filament\Pages\ChurnZoneReports::getUrl() }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-map-pin" />
                Zone collection
            </a>
            <a href="{{ \App\Filament\Pages\FiberPlantMap::getUrl() }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-globe-asia-australia" />
                Fiber GIS map
            </a>
            <a href="{{ \App\Filament\Pages\PrintReportsHub::getUrl() }}" class="isp-bi-domain-pill">
                <x-filament::icon icon="heroicon-o-arrow-down-tray" />
                Export center
            </a>
        </nav>

        @if(! empty($ai['recommendations']))
            <section class="isp-bi-section">
                <div class="isp-bi-section__head">
                    <div>
                        <h2 class="isp-bi-section__title">Business insights</h2>
                        <p class="isp-bi-section__desc">AI-assisted recommendations from existing operational data</p>
                    </div>
                </div>
                <div class="isp-bi-section__body space-y-2">
                    @foreach($ai['recommendations'] as $rec)
                        <div class="isp-bi-insight isp-bi-insight--{{ $rec['priority'] }}">
                            <span aria-hidden="true">●</span>
                            <span>{{ $rec['text'] }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        @endif

        <section class="isp-bi-section">
            <div class="isp-bi-section__head">
                <div>
                    <h2 class="isp-bi-section__title">Export center</h2>
                    <p class="isp-bi-section__desc">PDF, print, CSV — all existing export paths preserved</p>
                </div>
                <a href="{{ \App\Filament\Pages\PrintReportsHub::getUrl() }}" class="isp-bi-back">View all →</a>
            </div>
            <div class="isp-bi-section__body">
                <div class="isp-bi-export-dock">
                    <a href="{{ \App\Filament\Pages\DueReportPage::getUrl(['print' => 1]) }}" target="_blank" rel="noopener" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-printer" class="h-5 w-5" />
                        Print due report
                    </a>
                    <a href="{{ \App\Filament\Pages\PaymentsReport::getUrl() }}" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-table-cells" class="h-5 w-5" />
                        CSV payments
                    </a>
                    <a href="{{ \App\Filament\Pages\ExportClientsReport::getUrl() }}" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-arrow-down-tray" class="h-5 w-5" />
                        CSV clients
                    </a>
                    <a href="{{ \App\Filament\Pages\BtrcReport::getUrl() }}" class="isp-bi-export-btn">
                        <x-filament::icon icon="heroicon-o-document-arrow-down" class="h-5 w-5" />
                        BTRC DIS
                    </a>
                </div>
            </div>
        </section>

        <section class="isp-bi-section">
            <div class="isp-bi-section__head">
                <div>
                    <h2 class="isp-bi-section__title">Report catalog</h2>
                    <p class="isp-bi-section__desc">Every existing report — search by name, domain, customer, zone, or ticket</p>
                </div>
                <span class="text-sm text-gray-500" x-text="filtered.length + ' reports'"></span>
            </div>
            <div class="isp-bi-section__body">
                <div class="isp-bi-catalog">
                    <template x-for="item in filtered" :key="item.url">
                        <a :href="item.url" class="isp-bi-catalog-card">
                            <span class="isp-bi-catalog-card__icon">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-5 w-5"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" /></svg>
                            </span>
                            <div class="min-w-0">
                                <p class="isp-bi-catalog-card__eyebrow" x-text="item.domain"></p>
                                <p class="isp-bi-catalog-card__title" x-text="item.label"></p>
                                <p class="isp-bi-catalog-card__desc" x-text="item.hint"></p>
                            </div>
                        </a>
                    </template>
                </div>
                <p class="isp-bi-empty" x-show="filtered.length === 0">No reports match your search.</p>
            </div>
        </section>

        <x-isp.hub-footer />
    </div>

    @script
    <script>
        Alpine.data('ispBiHub', (catalog) => ({
            catalog,
            query: '',
            init() {
                this.$watch('query', (v) => this.filter(v));
                window.addEventListener('bi-search', (e) => this.filter(e.detail || ''));
            },
            filter(q) {
                this.query = (q || '').toLowerCase().trim();
            },
            get filtered() {
                if (!this.query) return this.catalog;
                return this.catalog.filter((item) =>
                    item.keywords.includes(this.query)
                    || item.label.toLowerCase().includes(this.query)
                    || item.domain.toLowerCase().includes(this.query)
                );
            },
        }));
    </script>
    @endscript
</x-filament-panels::page>
