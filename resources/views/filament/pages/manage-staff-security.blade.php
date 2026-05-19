<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-orange-50/30 p-6 dark:border-amber-900/40 dark:from-amber-950/40 dark:via-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Login security</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Control which IPs may access the admin panel and whether two-factor authentication is mandatory for your team.
            </p>
            <a href="{{ \App\Filament\Pages\StaffControlHub::getUrl() }}" class="mt-3 inline-block text-sm text-amber-700 hover:underline dark:text-amber-400">&larr; Staff control hub</a>
        </div>

        <form wire:submit="save" class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            {{ $this->form }}
            <div class="mt-6">
                <x-filament::button type="submit">Save settings</x-filament::button>
            </div>
        </form>
    </div>
</x-filament-panels::page>
