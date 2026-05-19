@props(['title', 'description' => null])

<div {{ $attributes->merge(['class' => 'isp-hub-hero']) }}>
    <h2 class="text-xl font-bold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h2>
    @if($description)
        <p class="mt-2 max-w-3xl text-sm text-gray-600 dark:text-gray-400">{{ $description }}</p>
    @endif
    {{ $slot }}
</div>
