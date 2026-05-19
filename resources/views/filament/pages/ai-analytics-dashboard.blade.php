@php $ai = $this->getInsights(); @endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.60s>
        <x-isp.hub-hero title="AI analytics" description="Predictive insights: churn risk, payment risk, fiber issues, revenue forecast." class="isp-hub-hero--violet" />

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="isp-module-card p-4"><p class="text-xs uppercase text-gray-500">Churn risk</p><p class="text-2xl font-bold">{{ $ai['churn_risk_customers'] }}</p><p class="text-xs text-gray-500">subscribers</p></div>
            <div class="isp-module-card p-4"><p class="text-xs uppercase text-gray-500">Payment risk</p><p class="text-2xl font-bold text-amber-600">{{ $ai['payment_risk_invoices'] }}</p><p class="text-xs text-gray-500">overdue invoices</p></div>
            <div class="isp-module-card p-4"><p class="text-xs uppercase text-gray-500">Fiber risk</p><p class="text-2xl font-bold text-rose-600">{{ $ai['fiber_risk_onus'] }}</p><p class="text-xs text-gray-500">weak/critical ONU</p></div>
            <div class="isp-module-card p-4"><p class="text-xs uppercase text-gray-500">Revenue forecast</p><p class="text-2xl font-bold text-emerald-600">{{ number_format($ai['revenue_forecast_mtd']) }}</p><p class="text-xs text-gray-500">BDT MTD est.</p></div>
        </div>

        <div class="isp-module-card p-4">
            <h3 class="font-semibold mb-3">AI recommendations</h3>
            <ul class="space-y-2">
                @foreach ($ai['recommendations'] as $rec)
                    <li class="rounded-lg border px-3 py-2 text-sm isp-ai-rec isp-ai-rec--{{ $rec['priority'] }}">{{ $rec['text'] }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</x-filament-panels::page>
