@php
    $logo = \App\Support\CompanyBranding::logoUrl();
    $initial = \App\Support\CompanyBranding::brandInitial();
@endphp
@if ($logo)
    <div class="isp-sidebar-brand-badge isp-sidebar-brand-badge--logo" aria-hidden="true">
        <img src="{{ $logo }}" alt="" />
    </div>
@else
    <div class="isp-sidebar-brand-badge" aria-hidden="true">{{ $initial }}</div>
@endif
