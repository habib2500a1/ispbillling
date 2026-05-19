@php
    $name = $companyName ?? \App\Support\CompanyBranding::name();
    $logo = $companyLogo ?? \App\Support\CompanyBranding::logoUrl();
    $initial = mb_strtoupper(mb_substr(trim($name), 0, 1, 'UTF-8'));
@endphp
@if ($logo)
    <img src="{{ $logo }}" alt="{{ $name }}" class="portal-brand-logo" loading="lazy" />
@else
    <span class="portal-brand-mark" aria-hidden="true">{{ $initial }}</span>
@endif
