@props(['sections' => [], 'optical' => [], 'urls' => []])

<div class="isp-cv-overview">
    @include('filament.resources.customer-resource.partials.client-details-sections', [
        'sections' => $sections,
        'keys' => ['account', 'billing', 'connection'],
        'compact' => true,
    ])

    <section class="isp-cv-card isp-cv-card--optical">
        <div class="isp-cv-card__head">
            <h3 class="isp-cv-card__title">Optical / ONU</h3>
            <button type="button" class="isp-cv-link" @click="tab = 'network'">Details →</button>
        </div>
        @if ($optical['linked'] ?? false)
            @php $row = ($optical['rows'][0] ?? []); @endphp
            <dl class="isp-cv-fields isp-cv-fields--inline">
                <div class="isp-cv-field">
                    <dt>RX</dt>
                    <dd class="font-mono">{{ $row['optical_power'] ?? '—' }} dBm</dd>
                </div>
                <div class="isp-cv-field">
                    <dt>TX</dt>
                    <dd class="font-mono">{{ $row['tx_power'] ?? '—' }} dBm</dd>
                </div>
                <div class="isp-cv-field">
                    <dt>OLT</dt>
                    <dd>{{ $row['olt_name'] ?? '—' }}</dd>
                </div>
                <div class="isp-cv-field">
                    <dt>PON</dt>
                    <dd class="font-mono text-xs">{{ $row['olt_port'] ?? '—' }}</dd>
                </div>
            </dl>
        @else
            <p class="isp-cv-muted text-sm">{{ $optical['hint'] ?? 'ONU not linked — open Network tab or Edit client.' }}</p>
        @endif
    </section>
</div>
