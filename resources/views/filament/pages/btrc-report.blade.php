@php $link = 'inline-flex font-medium text-primary-600 underline-offset-2 hover:underline dark:text-primary-400'; @endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <x-isp.hub-hero
            title="BTRC DIS report"
            description="Export active subscriber data with BTRC package labels for monthly regulatory submission (CSV)."
        />

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase text-gray-500">Rows ready</p>
                <p class="mt-1 text-3xl font-bold text-teal-600">{{ number_format($this->rowCount) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 sm:col-span-2">
                <p class="text-sm text-gray-600 dark:text-gray-400">
                    Ensure each package has <strong>BTRC label</strong> and <strong>bandwidth</strong> filled under
                    <a href="{{ \App\Filament\Resources\PackageResource::getUrl() }}" class="{{ $link }}">Packages</a>.
                </p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
