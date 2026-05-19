<x-filament-panels::page>
    <div class="mx-auto max-w-lg space-y-6">
        <a href="{{ \App\Filament\Pages\StaffControlHub::getUrl() }}" class="text-sm text-violet-600 hover:underline">&larr; Staff control hub</a>

        @if(auth()->user()?->hasTwoFactorEnabled())
            <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 p-6 dark:border-emerald-900 dark:bg-emerald-950/30">
                <p class="font-semibold text-emerald-800 dark:text-emerald-300">Two-factor authentication is enabled</p>
                <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Your account is protected with an authenticator app.</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button wire:click="regenerateRecoveryCodes" color="gray">Regenerate recovery codes</x-filament::button>
                    <x-filament::button wire:click="disable" color="danger" wire:confirm="Disable 2FA on your account?">Disable 2FA</x-filament::button>
                </div>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="font-semibold dark:text-white">Set up authenticator</h3>
                <p class="mt-2 text-sm text-gray-500">Scan the QR code with Google Authenticator, Authy, or similar.</p>
                @if($qrUrl)
                    <img src="{{ $qrUrl }}" alt="2FA QR code" class="mx-auto mt-4 rounded-lg border bg-white p-2" width="200" height="200">
                    <p class="mt-3 break-all text-center font-mono text-xs text-gray-500">{{ $pendingSecret }}</p>
                @endif
                <form wire:submit="enable" class="mt-4">
                    {{ $this->form }}
                    <x-filament::button type="submit" class="mt-4">Enable 2FA</x-filament::button>
                </form>
            </div>
        @endif

        @if(count($recoveryCodes) > 0)
            <div class="rounded-xl border border-amber-300 bg-amber-50 p-6 dark:border-amber-800 dark:bg-amber-950/40">
                <p class="font-semibold text-amber-900 dark:text-amber-200">Recovery codes (save now)</p>
                <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                    @foreach($recoveryCodes as $code)
                        <li class="rounded bg-white px-2 py-1 dark:bg-gray-900">{{ $code }}</li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</x-filament-panels::page>
