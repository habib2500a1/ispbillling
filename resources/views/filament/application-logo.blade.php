@php
    $logo = siteUrlSettings('site_logo');
    $icon = siteUrlSettings('site_icon');
    $name = site_brand();
@endphp

<div class="flex items-center">
    @if ($logo)
        <img src="{{ site_image($logo) }}" style="width: auto; height: auto; max-height: 53px; max-width: 190px; object-fit: contain;" alt="{{ $name }}">
    @elseif ($icon)
        <img src="{{ site_image($icon) }}" style="width: 40px; height: 40px; object-fit: contain;" alt="{{ $name }}">
        <span class="ml-2 font-bold text-xl">{{ $name }}</span>
    @else
        <span class="font-bold text-xl text-primary-600">{{ $name }}</span>
    @endif
</div>
