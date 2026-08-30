<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1e3a5f">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <!-- ===============================================-->
        <!--    Document Title-->
        <!-- ===============================================-->
        <title>{{ site_brand() }}</title>

        <link rel="shortcut icon" href="{{ site_image(siteUrlSettings('site_favicon'), 'images/favicon.png') }}" type="image/x-icon">

        @vite(['resources/sass/guest.scss', 'resources/js/guest.js'])
        <style>
            body { background-color: #f4f7fb !important; }
            h1, h2, .neon-text, .box__title, .form__title {
                color: #1e3a5f !important;
                text-shadow: none !important;
                animation: none !important;
            }
            .form__button { background-color: #1e3a5f !important; }
            .form__button:hover { background-color: #152a46 !important; }
            .form__link { color: #1e3a5f !important; }
            .input-group input { background: #e8eef5 !important; }
            .input-group input:focus { outline: 2px solid #1e3a5f; }
            section .air { filter: grayscale(1) opacity(0.2); }
        </style>
        <script>
            @php
                $tz = config('app.timezone', 'Asia/Dhaka');
                $phoneCountry = 'bd';
                if (class_exists('IntlTimeZone')) {
                    $region = \IntlTimeZone::getRegion($tz);
                    if ($region && strlen($region) === 2) {
                        $phoneCountry = strtolower($region);
                    }
                }
            @endphp
            window.sitePhoneCountry = '{{ $phoneCountry }}';
        </script>
    </head>
    <body>
        <div class="font-sans text-gray-900 dark:text-gray-100 antialiased">
            {{ $slot }}
        </div>
        <section>
            <div class='air air1'></div>
            <div class='air air2'></div>
            <div class='air air3'></div>
            <div class='air air4'></div>
        </section>
    </body>
</html>
