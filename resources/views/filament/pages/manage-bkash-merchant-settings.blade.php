<x-filament-panels::page>
    <div class="mb-4 rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-950 dark:border-rose-900/50 dark:bg-rose-950/30 dark:text-rose-100">
        <p class="font-bold">bKash Merchant API only</p>
        <p class="mt-1">এই পেজ শুধু official bKash checkout (redirect) সেভ করে। PipraPay বা অন্য gateway এখানে নেই।</p>
        <p class="mt-2 text-xs opacity-90">Other gateways:
            <a href="{{ \App\Filament\Pages\ManagePaymentSettings::getUrl(['gateway' => 'piprapay']) }}" class="underline">All merchant gateways</a>
        </p>
    </div>

    <x-filament-panels::form>
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
