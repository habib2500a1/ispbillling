@php
    $favicon = \App\Support\CompanyBranding::faviconUrl();
@endphp
@if (filled($favicon))
    <link rel="icon" href="{{ $favicon }}" @if (str_ends_with(strtolower($favicon), '.png')) type="image/png" @elseif (str_ends_with(strtolower($favicon), '.svg')) type="image/svg+xml" @else type="image/x-icon" @endif />
    <link rel="apple-touch-icon" href="{{ $favicon }}" />
@endif
