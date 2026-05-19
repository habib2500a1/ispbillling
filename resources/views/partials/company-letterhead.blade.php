@php
    use App\Support\CompanyBranding;

    $logoPath = CompanyBranding::logoAbsolutePath();
    $showLogo = CompanyBranding::invoiceShowLogo() && $logoPath !== null;
@endphp
<div class="company-letterhead">
    @if ($showLogo)
        <img src="{{ $logoPath }}" alt="{{ CompanyBranding::name() }}" class="company-letterhead__logo" />
    @endif
    <div class="company-letterhead__text">
        <p class="company-letterhead__name">{{ CompanyBranding::name() }}</p>
        @if (CompanyBranding::tagline())
            <p class="company-letterhead__tagline">{{ CompanyBranding::tagline() }}</p>
        @endif
        @if (CompanyBranding::address())
            <p class="company-letterhead__line">{{ CompanyBranding::address() }}</p>
        @endif
        @foreach (CompanyBranding::contactLines() as $line)
            <p class="company-letterhead__line">{{ $line }}</p>
        @endforeach
    </div>
</div>
