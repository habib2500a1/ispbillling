<x-filament-panels::page>
    <div class="mx-auto max-w-md space-y-6">
        <div class="rounded-2xl border border-violet-200 bg-violet-50/50 p-6 text-center dark:border-violet-900 dark:bg-violet-950/30">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Two-factor authentication</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Enter the 6-digit code from your authenticator app, or a recovery code.</p>
        </div>
        <form wire:submit="verify" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            {{ $this->form }}
            <x-filament::button type="submit" class="mt-4 w-full">Verify</x-filament::button>
        </form>
    </div>
</x-filament-panels::page>
