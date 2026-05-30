<div class="space-y-4 text-sm">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="font-semibold text-gray-900 dark:text-gray-100">Staff portal login</p>
        <p class="mt-1 text-gray-600 dark:text-gray-300">
            <span class="font-medium">Login ID:</span>
            <code class="rounded bg-white px-1 py-0.5 dark:bg-gray-800">{{ $login }}</code>
        </p>
        <p class="mt-1 text-gray-600 dark:text-gray-300">
            <span class="font-medium">Password:</span>
            @if ($passwordPlain)
                <code class="rounded bg-white px-1 py-0.5 dark:bg-gray-800">{{ $passwordPlain }}</code>
            @else
                <span class="text-amber-600 dark:text-amber-400">Not recorded — set a new password in Edit to store it here.</span>
            @endif
        </p>
        <p class="mt-2 text-xs text-gray-500">
            Portal URL:
            <a href="{{ $portalUrl }}" class="text-primary-600 underline" target="_blank" rel="noopener">{{ $portalUrl }}</a>
        </p>
    </div>
</div>
