<div class="space-y-4 text-sm">
    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="font-semibold text-gray-900 dark:text-gray-100">Password login</p>
        <p class="mt-1 text-gray-600 dark:text-gray-300">
            <span class="font-medium">Login ID:</span>
            <code class="rounded bg-white px-1 py-0.5 dark:bg-gray-800">{{ $login }}</code>
        </p>
        <p class="mt-1 text-gray-600 dark:text-gray-300">
            <span class="font-medium">Password:</span>
            @if ($passwordPlain)
                <code class="rounded bg-white px-1 py-0.5 dark:bg-gray-800">{{ $passwordPlain }}</code>
            @else
                <span class="text-amber-600 dark:text-amber-400">Not recorded — use “Reset portal password” to set and reveal.</span>
            @endif
        </p>
        <p class="mt-2 text-xs text-gray-500">Portal URL: <a href="{{ route('reseller.login') }}" class="text-primary-600 underline" target="_blank" rel="noopener">{{ route('reseller.login') }}</a></p>
    </div>

    <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-800 dark:bg-amber-950/40">
        <p class="font-semibold text-amber-900 dark:text-amber-100">Token login (one-click)</p>
        <p class="mt-1 break-all font-mono text-xs text-amber-950 dark:text-amber-50">{{ $token }}</p>
        <p class="mt-2 break-all text-xs text-amber-800 dark:text-amber-200">{{ $link }}</p>
        <p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Open this link to log in as this partner without a password. Regenerate the token if it is compromised.</p>
    </div>
</div>
