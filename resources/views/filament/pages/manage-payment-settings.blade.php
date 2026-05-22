@php
    $personalUrl = \App\Filament\Pages\ManagePersonalMfsSettings::getUrl(['tab' => 'bkash']);
@endphp

<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-sky-300 bg-sky-50 p-4 text-sm text-sky-950 dark:border-sky-800 dark:bg-sky-950/40 dark:text-sky-100">
        <p class="font-bold">Merchant payment gateways</p>
        <p class="mt-1">PipraPay, bKash Tokenized API, Nagad PG, SSLCommerz, Rocket — official merchant checkout।</p>
        <p class="mt-2">
            <strong>Personal bKash/Nagad</strong> (Send Money + SMS verify) আলাদা:
            <a href="{{ $personalUrl }}" class="font-semibold underline text-primary-600">Personal MFS verify →</a>
        </p>
    </div>

    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    <div class="mt-6 rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm text-teal-900 dark:border-teal-900/50 dark:bg-teal-950/40 dark:text-teal-200">
        <p class="font-semibold">Public bill payment</p>
        <p class="mt-1 text-teal-800/90 dark:text-teal-200/90">Customers pay without login at:</p>
        <a href="{{ route('bill-payment.index') }}" target="_blank" class="mt-2 inline-block font-mono text-xs underline">{{ route('bill-payment.index') }}</a>
    </div>
</x-filament-panels::page>
