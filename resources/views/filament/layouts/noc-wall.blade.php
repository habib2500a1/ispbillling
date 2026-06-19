<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#020617">
    <title>NOC Wall — {{ \App\Support\CompanyBranding::name() }}</title>
    @if ($favicon = \App\Support\CompanyBranding::faviconUrl())
        <link rel="icon" href="{{ $favicon }}" />
    @endif
    {!! \App\Support\AdminSaasStyles::html() !!}
    <link rel="stylesheet" href="{{ asset('css/optical-noc.css') }}">
</head>
<body class="isp-noc-wall-body" data-isp-noc-wall="1" data-noc-stream="{{ route('admin.noc-wall-stream') }}">
    @php($broadcast = app(\App\Support\AdminBroadcastConfig::class)->forUser(auth()->user()))
    @if ($broadcast['enabled'] ?? false)
        <script>window.ISP_BROADCAST = @json($broadcast);</script>
        <script src="https://cdn.jsdelivr.net/npm/pusher-js@8.4.0/dist/web/pusher.min.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/laravel-echo@1.16.1/dist/echo.iife.js" defer></script>
    @endif
    {{ $slot }}
    <script src="{{ asset('js/isp-noc-wall-realtime.js') }}?v={{ filemtime(public_path('js/isp-noc-wall-realtime.js')) }}" defer></script>
</body>
</html>
