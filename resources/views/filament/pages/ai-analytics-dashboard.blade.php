@php
    $ai = $this->getInsights();
    $insightCards = [
        ['label' => 'Churn risk', 'value' => $ai['churn_risk_customers'], 'meta' => 'subscribers', 'class' => ''],
        ['label' => 'Payment risk', 'value' => $ai['payment_risk_invoices'], 'meta' => 'overdue invoices', 'class' => 'text-amber-600'],
        ['label' => 'Fiber risk', 'value' => $ai['fiber_risk_onus'], 'meta' => 'weak/critical ONU', 'class' => 'text-rose-600'],
        ['label' => 'Revenue forecast', 'value' => number_format($ai['revenue_forecast_mtd']), 'meta' => 'BDT MTD est.', 'class' => 'text-emerald-600'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.60s>
        <x-isp.hub-hero
            eyebrow="Predictive operations"
            title="AI analytics"
            description="Predictive insights for churn risk, payment risk, fiber issues, and revenue forecast."
            class="isp-hub-hero--violet"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ count($ai['recommendations']) }} recommendations</span>
                    <span class="isp-hub-section__meta">Refresh 60s</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($insightCards as $card)
                <div class="isp-ai-card">
                    <p class="isp-ai-card__label">{{ $card['label'] }}</p>
                    <p class="isp-ai-card__value {{ $card['class'] }}">{{ $card['value'] }}</p>
                    <p class="isp-ai-card__meta">{{ $card['meta'] }}</p>
                </div>
            @endforeach
        </div>

        <section class="isp-ops-panel">
            <div class="isp-ops-panel__head">
                <div>
                    <h3 class="isp-ops-panel__title">AI recommendations</h3>
                    <p class="isp-ops-panel__desc">Actionable guidance generated from current churn, payment, and network risk signals.</p>
                </div>
                <span class="isp-ops-pill isp-ops-pill--warn">Priority ranked</span>
            </div>
            <ul class="space-y-2 p-4 pt-0">
                @foreach ($ai['recommendations'] as $rec)
                    <li class="rounded-lg border px-3 py-2 text-sm isp-ai-rec isp-ai-rec--{{ $rec['priority'] }}">{{ $rec['text'] }}</li>
                @endforeach
            </ul>
        </section>
    </div>
</x-filament-panels::page>
