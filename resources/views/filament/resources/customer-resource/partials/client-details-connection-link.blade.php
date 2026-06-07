@props(['link' => []])

@php
    $connType = $link['conn_type'] ?? '—';
    $device = $link['device'] ?? '—';
    $serial = $link['serial_no'] ?? '—';
    $ownershipLabel = $link['onu_ownership_label'] ?? 'ONU';
    $ownershipTone = $link['onu_ownership_tone'] ?? 'company';
    $length = $link['length_distance'] ?? '—';
    $connectedBy = $link['connected_by'] ?? '—';
    $routerUser = $link['router_username'] ?? '—';
    $routerPass = $link['router_password_display'] ?? '—';
@endphp

<section class="isp-cv-card isp-cv-card--connection-link">
    <h3 class="isp-cv-card__title isp-cv-card__title--decor">
        <span class="isp-cv-card__title-line" aria-hidden="true"></span>
        Connection Link Info
        <span class="isp-cv-card__title-line" aria-hidden="true"></span>
    </h3>

    <dl class="isp-cv-fields isp-cv-fields--connection-link">
        <div class="isp-cv-field">
            <dt>Conn. Type</dt>
            <dd>
                <span class="isp-cv-pill isp-cv-pill--fiber">{{ $connType }}</span>
            </dd>
        </div>
        <div class="isp-cv-field">
            <dt>Device</dt>
            <dd>{{ $device }}</dd>
        </div>
        <div class="isp-cv-field isp-cv-field--serial">
            <dt>Serial No</dt>
            <dd class="isp-cv-field__serial-row">
                <span class="font-mono">{{ $serial }}</span>
                <span class="isp-cv-onu-badge isp-cv-onu-badge--{{ $ownershipTone }}">{{ $ownershipLabel }}</span>
            </dd>
        </div>
        <div class="isp-cv-field">
            <dt>Length/Distance</dt>
            <dd>{{ $length }}</dd>
        </div>
        <div class="isp-cv-field">
            <dt>Connected By</dt>
            <dd>{{ $connectedBy }}</dd>
        </div>
    </dl>

    <h3 class="isp-cv-card__title isp-cv-card__title--decor isp-cv-card__title--sub">
        <span class="isp-cv-card__title-line" aria-hidden="true"></span>
        Router Details
        <span class="isp-cv-card__title-line" aria-hidden="true"></span>
    </h3>

    <dl class="isp-cv-fields isp-cv-fields--connection-link">
        <div class="isp-cv-field">
            <dt>Username</dt>
            <dd class="font-mono text-sm">{{ $routerUser }}</dd>
        </div>
        <div class="isp-cv-field">
            <dt>Password</dt>
            <dd class="font-mono tracking-widest">{{ $routerPass }}</dd>
        </div>
    </dl>
</section>
