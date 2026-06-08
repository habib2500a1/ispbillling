@php
    $file = $file ?? null;
    $path = is_string($file) && $file !== '' ? public_path('css/'.$file) : null;
@endphp
@if ($path && is_file($path))
    <link rel="stylesheet" href="{{ asset('css/'.$file) }}?v={{ @filemtime($path) ?: 1 }}">
@endif
