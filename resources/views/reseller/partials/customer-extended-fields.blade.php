@php
    $meta = is_array(($customer ?? null)?->meta) ? $customer->meta : [];
@endphp

<section class="rsl-form-section" id="tags">
    <h2 class="rsl-form-section-title">Tags & alerts</h2>
    <div class="rsl-tag-grid">
        <label class="rsl-field-check"><input type="checkbox" name="tag_vip" value="1" @checked(old('tag_vip', $meta['tag_vip'] ?? false))><span>VIP</span></label>
        <label class="rsl-field-check"><input type="checkbox" name="tag_late_payer" value="1" @checked(old('tag_late_payer', $meta['tag_late_payer'] ?? false))><span>Late payer</span></label>
        <label class="rsl-field-check"><input type="checkbox" name="tag_gaming" value="1" @checked(old('tag_gaming', $meta['tag_gaming'] ?? false))><span>Gaming</span></label>
        <label class="rsl-field-check"><input type="checkbox" name="tag_corporate" value="1" @checked(old('tag_corporate', $meta['tag_corporate'] ?? false))><span>Corporate</span></label>
    </div>
    <p class="rsl-form-section-hint">Use tags to filter subscribers and target reminders.</p>
    <div class="rsl-tag-grid mt-2">
        <label class="rsl-field-check"><input type="checkbox" name="notify_sms" value="1" @checked(old('notify_sms', $meta['notify_sms'] ?? true))><span>SMS alerts</span></label>
        <label class="rsl-field-check"><input type="checkbox" name="notify_whatsapp" value="1" @checked(old('notify_whatsapp', $meta['notify_whatsapp'] ?? false))><span>WhatsApp alerts</span></label>
        <label class="rsl-field-check"><input type="checkbox" name="notify_email" value="1" @checked(old('notify_email', $meta['notify_email'] ?? false))><span>Email alerts</span></label>
    </div>
</section>

<section class="rsl-form-section" id="payment-plan">
    <h2 class="rsl-form-section-title">Payment plan (installments)</h2>
    <label class="rsl-field-check">
        <input type="checkbox" name="payment_plan_enabled" value="1" @checked(old('payment_plan_enabled', $meta['payment_plan_enabled'] ?? false))>
        <span>Enable installment plan</span>
    </label>
    <div class="rsl-form-grid rsl-form-grid--2">
        <div class="rsl-field">
            <label class="rsl-field-label" for="payment_plan_installment_bdt">Installment amount (BDT)</label>
            <input type="number" step="0.01" min="0" id="payment_plan_installment_bdt" name="payment_plan_installment_bdt"
                value="{{ old('payment_plan_installment_bdt', $meta['payment_plan_installment_bdt'] ?? '') }}" class="rsl-input" placeholder="e.g. 500">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="payment_plan_next_due_date">Next installment due</label>
            <input type="date" id="payment_plan_next_due_date" name="payment_plan_next_due_date"
                value="{{ old('payment_plan_next_due_date', $meta['payment_plan_next_due_date'] ?? '') }}" class="rsl-input">
        </div>
    </div>
    <div class="rsl-field">
        <label class="rsl-field-label" for="payment_plan_note">Plan note</label>
        <input type="text" id="payment_plan_note" name="payment_plan_note" maxlength="255"
            value="{{ old('payment_plan_note', $meta['payment_plan_note'] ?? '') }}" class="rsl-input" placeholder="e.g. 4 installments of 500 BDT">
    </div>
    <p class="rsl-field-hint">Collect partial payments on the Collect payment screen — amount can be less than full due.</p>
</section>

<section class="rsl-form-section" id="network-bind">
    <h2 class="rsl-form-section-title">Network binding</h2>
    <div class="rsl-form-grid rsl-form-grid--2">
        <div class="rsl-field">
            <label class="rsl-field-label" for="mac_binding">Router / MAC binding</label>
            <input id="mac_binding" name="mac_binding" value="{{ old('mac_binding', $meta['mac_binding'] ?? '') }}" class="rsl-input font-mono" placeholder="AA:BB:CC:DD:EE:FF">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="onu_mac">ONU MAC</label>
            <input id="onu_mac" name="onu_mac" value="{{ old('onu_mac', $meta['onu_mac'] ?? '') }}" class="rsl-input font-mono">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="epon_port">EPON port</label>
            <input id="epon_port" name="epon_port" value="{{ old('epon_port', $meta['epon_port'] ?? '') }}" class="rsl-input">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="vlan">VLAN</label>
            <input id="vlan" name="vlan" value="{{ old('vlan', $meta['vlan'] ?? '') }}" class="rsl-input">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="static_ip">Static IP</label>
            <input id="static_ip" name="static_ip" value="{{ old('static_ip', $meta['static_ip'] ?? '') }}" class="rsl-input font-mono">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="installation_date">Installation date</label>
            <input type="date" id="installation_date" name="installation_date"
                value="{{ old('installation_date', $meta['installation_date'] ?? '') }}" class="rsl-input">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="gps_lat">GPS latitude</label>
            <input type="number" step="any" id="gps_lat" name="gps_lat" value="{{ old('gps_lat', $meta['gps_lat'] ?? '') }}" class="rsl-input" placeholder="23.8103">
        </div>
        <div class="rsl-field">
            <label class="rsl-field-label" for="gps_lng">GPS longitude</label>
            <input type="number" step="any" id="gps_lng" name="gps_lng" value="{{ old('gps_lng', $meta['gps_lng'] ?? '') }}" class="rsl-input" placeholder="90.4125">
        </div>
    </div>
</section>
