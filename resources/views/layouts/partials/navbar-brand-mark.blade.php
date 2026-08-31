<a class="navbar-brand {{ $class ?? '' }}" href="{{ staffHomeUrl() }}">
    <div class="d-flex align-items-center {{ siteUrlSettings('site_logo') ? 'py-1' : ($py ?? '') }}">
        @if (siteUrlSettings('site_logo'))
            <img class="me-2 navbar-brand-logo" style="{{ $logoStyle ?? 'width: 150px; height: auto; max-height: 45px; max-width: 100%; object-fit: contain; flex-shrink: 0;' }}"
                src="{{ site_image(siteUrlSettings('site_logo')) }}" alt="{{ site_brand() }}">
        @elseif (siteUrlSettings('site_icon'))
            <img class="me-2" src="{{ site_image(siteUrlSettings('site_icon')) }}" alt="" width="40">
            <span class="font-sans-serif brand-wordmark">{{ site_brand() }}</span>
        @else
            <span class="font-sans-serif brand-wordmark">{{ site_brand() }}</span>
        @endif
    </div>
</a>
