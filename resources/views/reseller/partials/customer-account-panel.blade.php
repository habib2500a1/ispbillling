@php
    $p = $pricing ?? [];
    $meta = is_array($customer->meta) ? $customer->meta : [];
@endphp

<div class="rsl-panel rsl-panel-pad">
    <h2 class="rsl-panel-title">Account details</h2>
    <dl class="rsl-detail-list">
        <div><dt>Client ID</dt><dd class="font-mono">{{ $customer->customer_code }}</dd></div>
        <div><dt>Phone</dt><dd><a href="tel:{{ $customer->phone }}" class="rsl-link">{{ $customer->phone }}</a></dd></div>
        @if ($customer->email)
            <div><dt>Email</dt><dd>{{ $customer->email }}</dd></div>
        @endif
        @if ($customer->telegram_chat_id)
            <div><dt>Telegram</dt><dd class="font-mono">{{ $customer->telegram_chat_id }}</dd></div>
        @endif
        <div><dt>Address</dt><dd>{{ $customer->address ?: '—' }}</dd></div>
        <div><dt>Area / Zone</dt><dd>{{ $customer->area?->name ?? '—' }} / {{ $customer->zone?->name ?? '—' }}</dd></div>
        <div><dt>Billing day</dt><dd>Day {{ $p['billing_day'] ?? $customer->billing_day ?? 1 }} of month</dd></div>
        <div><dt>Joined</dt><dd>{{ $p['joined_at'] ?? ($customer->joined_at?->format('d M Y') ?? '—') }}</dd></div>
        @if (filled(data_get($meta, 'legacy_portal_validity_to')))
            <div><dt>Validity</dt><dd>{{ data_get($meta, 'legacy_portal_validity_to') }}</dd></div>
        @endif
        @if (filled(data_get($meta, 'server_name')))
            <div><dt>Server</dt><dd>{{ data_get($meta, 'server_name') }}</dd></div>
        @endif
        @if (filled(data_get($meta, 'connection_type')))
            <div><dt>Connection</dt><dd>{{ data_get($meta, 'connection_type') }}</dd></div>
        @endif
        <div><dt>First month</dt><dd>{{ $p['charge_mode_label'] ?? '—' }}</dd></div>
        <div><dt>Grace</dt><dd>{{ (int) ($customer->grace_period_days ?? 0) }} days ({{ $customer->billing_mode ?? 'prepaid' }})</dd></div>
        @if (data_get($meta, 'allow_active_when_due'))
            <div><dt>Due policy</dt><dd>Keep online when due</dd></div>
        @endif
        @if (filled($customer->notes))
            <div class="rsl-detail-list--full"><dt>Notes</dt><dd>{{ $customer->notes }}</dd></div>
        @endif
    </dl>
</div>
