<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900/50 dark:bg-amber-950/30 dark:text-amber-100">
        <p class="font-semibold">ONU laser power — fixed thresholds</p>
        <p class="mt-1">Subscriber view ও Optical NOC-এ RX/TX dBm এই সীমার উপর ভিত্তি করে রঙ ও “Laser high” দেখাবে। Laser বেশি হলে সীমা এখানে ঠিক করে নিন (যেমন RX high = -8 dBm)।</p>
    </div>

    <x-filament-panels::form id="form" wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
