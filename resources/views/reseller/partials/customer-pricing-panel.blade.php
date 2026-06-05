@php
    $p = $pricing ?? [];
@endphp

<div class="rsl-panel rsl-panel-pad rsl-pricing-panel">
    <div class="rsl-panel-head rsl-panel-head--inline">
        <h2 class="rsl-panel-title">Pricing & margin</h2>
        @if ($portal->canPortal(\App\Support\ResellerPortalPermission::CUSTOMER_EDIT))
            <a href="{{ route('reseller.customers.edit', $customer) }}#pricing" class="rsl-link-action">Edit prices</a>
        @endif
    </div>

    <div class="rsl-pricing-grid">
        <div class="rsl-pricing-cell rsl-pricing-cell--sell">
            <p class="rsl-pricing-label">Sell price (monthly)</p>
            <p class="rsl-pricing-value">{{ number_format($p['retail_monthly'] ?? 0, 0) }} <small>BDT</small></p>
            @if (! empty($p['retail_override']))
                <p class="rsl-pricing-note">Custom price (list {{ number_format($p['catalog_retail_monthly'] ?? 0, 0) }})</p>
            @elseif (($p['monthly_discount'] ?? 0) > 0)
                <p class="rsl-pricing-note">Discount −{{ number_format($p['monthly_discount'], 0) }} BDT</p>
            @endif
        </div>
        <div class="rsl-pricing-cell rsl-pricing-cell--buy">
            <p class="rsl-pricing-label">Your buy rate (HQ)</p>
            <p class="rsl-pricing-value">
                @if ($p['wholesale_monthly'] !== null)
                    {{ number_format($p['wholesale_monthly'], 0) }} <small>BDT</small>
                @else
                    <span class="rsl-text-muted">—</span>
                @endif
            </p>
        </div>
        <div class="rsl-pricing-cell rsl-pricing-cell--margin">
            <p class="rsl-pricing-label">Est. margin / mo</p>
            <p class="rsl-pricing-value rsl-pricing-value--profit">
                @if ($p['margin_monthly'] !== null)
                    {{ number_format($p['margin_monthly'], 0) }} <small>BDT</small>
                    @if ($p['margin_percent'] !== null)
                        <span class="rsl-pricing-pct">{{ $p['margin_percent'] }}%</span>
                    @endif
                @else
                    <span class="rsl-text-muted">—</span>
                @endif
            </p>
        </div>
        <div class="rsl-pricing-cell">
            <p class="rsl-pricing-label">Next bill (est.)</p>
            <p class="rsl-pricing-value text-base">{{ number_format($p['estimated_cycle_retail'] ?? 0, 0) }} BDT</p>
            @if (($p['estimated_cycle_wholesale'] ?? 0) > 0)
                <p class="rsl-pricing-note">HQ {{ number_format($p['estimated_cycle_wholesale'], 0) }} · margin {{ number_format($p['estimated_cycle_margin'] ?? 0, 0) }}</p>
            @endif
        </div>
    </div>

    @if (($p['onu_rent'] ?? 0) > 0 || ($p['router_rent'] ?? 0) > 0 || ($p['installation_charge'] ?? 0) > 0)
        <dl class="rsl-detail-list rsl-detail-list--compact">
            @if (($p['onu_rent'] ?? 0) > 0)
                <div><dt>ONU rent</dt><dd>{{ number_format($p['onu_rent'], 0) }} BDT/mo</dd></div>
            @endif
            @if (($p['router_rent'] ?? 0) > 0)
                <div><dt>Router rent</dt><dd>{{ number_format($p['router_rent'], 0) }} BDT/mo</dd></div>
            @endif
            @if (($p['installation_charge'] ?? 0) > 0)
                <div><dt>Installation</dt><dd>{{ number_format($p['installation_charge'], 0) }} BDT</dd></div>
            @endif
        </dl>
    @endif

    @if (! empty($p['discount_note']))
        <p class="rsl-pricing-footnote"><strong>Note:</strong> {{ $p['discount_note'] }}</p>
    @endif
</div>
