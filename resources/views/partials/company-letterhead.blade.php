@php
    $logoPath = \App\Support\CompanyBranding::logoAbsolutePath();
    $showLogo = \App\Support\CompanyBranding::invoiceShowLogo() && $logoPath !== null;
@endphp
<div class="company-letterhead">
    @if ($showLogo)
        <img src="{{ $logoPath }}" alt="{{ \App\Support\CompanyBranding::name() }}" class="company-letterhead__logo" />
    @endif
    <div class="company-letterhead__text">
        <p class="company-letterhead__name">{{ \App\Support\CompanyBranding::name() }}</p>
        @if (\App\Support\CompanyBranding::tagline())
            <p class="company-letterhead__tagline">{{ \App\Support\CompanyBranding::tagline() }}</p>
        @endif
        @if (\App\Support\CompanyBranding::address())
            <p class="company-letterhead__line">{{ \App\Support\CompanyBranding::address() }}</p>
        @endif
        @foreach (\App\Support\CompanyBranding::contactLines() as $line)
            <p class="company-letterhead__line">{{ $line }}</p>
        @endforeach
    </div>
</div>
