<x-filament-panels::page>
    <div class="mx-auto max-w-3xl space-y-6">
        <x-isp.hub-hero
            eyebrow="Account protection"
            title="Two-factor setup"
            description="Protect your account with an authenticator app and recovery codes before accessing sensitive admin actions."
            class="isp-hub-hero--violet"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">{{ auth()->user()?->hasTwoFactorEnabled() ? '2FA enabled' : '2FA pending' }}</span>
                    <span class="isp-hub-section__meta">Authenticator required</span>
                </div>
                <a href="{{ \App\Filament\Pages\StaffControlHub::getUrl() }}" class="isp-quick-pill">Staff control hub</a>
            </div>
        </x-isp.hub-hero>

        @if(auth()->user()?->hasTwoFactorEnabled())
            <section class="isp-ops-panel">
                <div class="isp-ops-panel__head">
                    <div>
                        <h3 class="isp-ops-panel__title">Two-factor authentication is enabled</h3>
                        <p class="isp-ops-panel__desc">Your account is protected with an authenticator app and recovery workflow.</p>
                    </div>
                    <span class="isp-ops-pill isp-ops-pill--ok">Protected</span>
                </div>
                <div class="p-4 pt-0">
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-filament::button wire:click="regenerateRecoveryCodes" color="gray">Regenerate recovery codes</x-filament::button>
                    <x-filament::button wire:click="disable" color="danger" wire:confirm="Disable 2FA on your account?">Disable 2FA</x-filament::button>
                </div>
                </div>
            </section>
        @else
            <section class="isp-ops-panel">
                <div class="isp-ops-panel__head">
                    <div>
                        <h3 class="isp-ops-panel__title">Set up authenticator</h3>
                        <p class="isp-ops-panel__desc">Scan the QR code with Google Authenticator, Authy, or a compatible app, then verify the generated code.</p>
                    </div>
                    <span class="isp-ops-pill isp-ops-pill--warn">Setup required</span>
                </div>
                <div class="p-4 pt-0">
                @if($qrUrl)
                    <img src="{{ $qrUrl }}" alt="2FA QR code" class="mx-auto mt-4 rounded-lg border bg-white p-2" width="200" height="200">
                    <p class="mt-3 break-all text-center font-mono text-xs text-gray-500">{{ $pendingSecret }}</p>
                @endif
                <form wire:submit="enable" class="mt-4">
                    {{ $this->form }}
                    <x-filament::button type="submit" class="mt-4">Enable 2FA</x-filament::button>
                </form>
                </div>
            </section>
        @endif

        @if(count($recoveryCodes) > 0)
            <section class="isp-ops-panel">
                <div class="isp-ops-panel__head">
                    <div>
                        <h3 class="isp-ops-panel__title">Recovery codes</h3>
                        <p class="isp-ops-panel__desc">Save these codes in a secure place. They can be used if your authenticator device is unavailable.</p>
                    </div>
                    <span class="isp-ops-pill isp-ops-pill--warn">Save now</span>
                </div>
                <div class="p-4 pt-0">
                <ul class="mt-3 grid grid-cols-2 gap-2 font-mono text-sm">
                    @foreach($recoveryCodes as $code)
                        <li class="rounded bg-white px-2 py-1 dark:bg-gray-900">{{ $code }}</li>
                    @endforeach
                </ul>
                </div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
