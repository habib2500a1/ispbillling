@php
    $meta = is_array(($customer ?? null)?->meta) ? $customer->meta : [];
    $p = $pricing ?? null;
@endphp

<section class="rsl-form-section">
    <h2 class="rsl-form-section-title">Pricing & margin</h2>
    <p class="rsl-form-section-hint">Sell price is what the subscriber pays. Buy price (your rate) is set by HQ per package.</p>

    @if ($p && $p['wholesale_monthly'] !== null)
        <div class="rsl-pricing-hint">
            <span>HQ buy rate</span>
            <strong>{{ number_format($p['wholesale_monthly'], 0) }} BDT/mo</strong>
            <span class="rsl-pricing-hint-sep">·</span>
            <span>Package list</span>
            <strong>{{ number_format($p['catalog_retail_monthly'], 0) }} BDT/mo</strong>
        </div>
    @endif

    <div class="rsl-form-grid rsl-form-grid--2">
        <div class="rsl-field">
            <label class="rsl-field-label" for="reseller_retail_monthly_bdt">Custom sell price (BDT/mo)</label>
            <input type="number" step="0.01" min="0" id="reseller_retail_monthly_bdt" name="reseller_retail_monthly_bdt"
                value="{{ old('reseller_retail_monthly_bdt', $meta['reseller_retail_monthly_bdt'] ?? '') }}"
                class="rsl-input" placeholder="Leave blank = package price">
            <p class="rsl-field-hint">Overrides package list price for this subscriber only.</p>
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="monthly_discount_bdt">Monthly discount (BDT)</label>
            <input type="number" step="0.01" min="0" id="monthly_discount_bdt" name="monthly_discount_bdt"
                value="{{ old('monthly_discount_bdt', $meta['monthly_discount_bdt'] ?? '') }}"
                class="rsl-input" placeholder="0">
            <p class="rsl-field-hint">Subtracts from sell price when no custom price is set.</p>
        </div>
    </div>
    <div class="rsl-field">
        <label class="rsl-field-label" for="discount_note">Discount note</label>
        <input type="text" id="discount_note" name="discount_note" maxlength="255"
            value="{{ old('discount_note', $meta['discount_note'] ?? '') }}" class="rsl-input" placeholder="e.g. Loyalty discount">
    </div>
</section>

<section class="rsl-form-section">
    <h2 class="rsl-form-section-title">Equipment & one-time fees</h2>
    <div class="rsl-form-grid rsl-form-grid--2">
        <div class="rsl-field">
            <label class="rsl-field-label" for="onu_rent">ONU rent (BDT/mo)</label>
            <input type="number" step="0.01" min="0" id="onu_rent" name="onu_rent"
                value="{{ old('onu_rent', $meta['onu_rent'] ?? '') }}" class="rsl-input">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="router_rent">Router rent (BDT/mo)</label>
            <input type="number" step="0.01" min="0" id="router_rent" name="router_rent"
                value="{{ old('router_rent', $meta['router_rent'] ?? '') }}" class="rsl-input">
        </div>
    </div>
    <div class="rsl-field">
        <label class="rsl-field-label" for="installation_charge">Installation charge (BDT)</label>
        <input type="number" step="0.01" min="0" id="installation_charge" name="installation_charge"
            value="{{ old('installation_charge', $meta['installation_charge'] ?? '') }}" class="rsl-input">
    </div>
</section>
