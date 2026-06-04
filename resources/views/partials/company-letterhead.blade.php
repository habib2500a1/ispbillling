@php
    $brandName = $letterheadName ?? \App\Support\CompanyBranding::name();
    $logoPath = $letterheadLogoPath ?? \App\Support\CompanyBranding::logoAbsolutePath();
    $showLogo = $letterheadShowLogo ?? (\App\Support\CompanyBranding::invoiceShowLogo() && $logoPath !== null);
    $tagline = $letterheadTagline ?? \App\Support\CompanyBranding::tagline();
    $address = $letterheadAddress ?? \App\Support\CompanyBranding::address();
    $contactLines = $letterheadContactLines ?? \App\Support\CompanyBranding::contactLines();
@endphp
<div class="company-letterhead">
    @if ($showLogo && $logoPath)
        <img src="{{ $logoPath }}" alt="{{ $brandName }}" class="company-letterhead__logo" />
    @endif
    <div class="company-letterhead__text">
        <p class="company-letterhead__name">{{ $brandName }}</p>
        @if ($tagline)
            <p class="company-letterhead__tagline">{{ $tagline }}</p>
        @endif
        @if ($address)
            <p class="company-letterhead__line">{{ $address }}</p>
        @endif
        @foreach ($contactLines as $line)
            <p class="company-letterhead__line">{{ $line }}</p>
        @endforeach
    </div>
</div>
