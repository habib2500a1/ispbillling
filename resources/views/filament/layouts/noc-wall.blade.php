<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>NOC Wall — {{ config('isp.company_name') }}</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="{{ asset('css/admin-saas.css') }}">
</head>
<body class="isp-noc-wall-body" data-isp-dashboard="1">
    {{ $slot }}
    <script src="{{ asset('js/isp-dashboard-realtime.js') }}?v={{ filemtime(public_path('js/isp-dashboard-realtime.js')) }}" defer></script>
</body>
</html>
