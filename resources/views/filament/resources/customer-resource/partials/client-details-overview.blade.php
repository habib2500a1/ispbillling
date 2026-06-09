@props(['sections' => [], 'optical' => [], 'urls' => [], 'notes' => null, 'connectionLink' => [], 'contact' => [], 'address' => null, 'clientName' => null, 'editUrl' => null])

@php
    $accountSection = $sections['account'] ?? [];

    foreach (['Collector', 'Connected by', 'Branch'] as $staffLabel) {
        $value = $sections['staff'][$staffLabel] ?? null;

        if (filled($value) && $value !== '—') {
            $accountSection[$staffLabel] = $value;
        }
    }

    $overviewSections = array_merge($sections, ['account' => $accountSection]);
@endphp

<div class="isp-cv-overview">
    <div class="isp-cv-overview__main">
        @include('filament.resources.customer-resource.partials.client-details-connection-link', [
            'link' => $connectionLink ?? [],
        ])

        @include('filament.resources.customer-resource.partials.client-details-sections', [
            'sections' => $overviewSections,
            'keys' => ['billing'],
            'compact' => true,
            'contact' => $contact ?? [],
        ])
    </div>

    <aside class="isp-cv-overview__side">
        @include('filament.resources.customer-resource.partials.client-details-sections', [
            'sections' => $overviewSections,
            'keys' => ['account'],
            'compact' => true,
            'contact' => $contact ?? [],
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

        @if (filled($notes))
            <section class="isp-cv-card isp-cv-card--notes">
                <div class="isp-cv-card__head">
                    <h3 class="isp-cv-card__title">Quick notes</h3>
                </div>
                <p class="isp-cv-notes">{{ $notes }}</p>
            </section>
        @endif
    </aside>
</div>

@include('filament.resources.customer-resource.partials.client-details-location', [
    'contact' => $contact,
    'address' => $address,
    'editUrl' => $editUrl,
    'clientName' => $clientName,
])
