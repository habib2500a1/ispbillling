@php
    $livewire ??= null;
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    <div class="isp-premium-orbs" aria-hidden="true">
        <span></span><span></span><span></span>
    </div>
    <div class="isp-auth-split min-h-screen">
        <aside class="isp-auth-split__brand">
            <div class="isp-auth-split__brand-inner">
                @if($logo = \App\Support\CompanyBranding::logoUrl())
                    <img src="{{ $logo }}" alt="" class="isp-auth-split__logo mb-6 max-h-16 w-auto">
                @else
                    <div class="isp-auth-split__logo-mark" aria-hidden="true">
                        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="2" opacity="0.35"/>
                            <path d="M14 28 L24 14 L34 28" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M18 32 H30" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"/>
                        </svg>
                    </div>
                @endif
                <h1 class="isp-auth-split__company">{{ \App\Support\CompanyBranding::name() }}</h1>
                <p class="isp-auth-split__tagline">{{ \App\Support\CompanyBranding::tagline() }}</p>
                <ul class="isp-auth-split__features mt-10 space-y-3 text-sm text-white/75">
                    <li>Billing · payments · auto disconnect</li>
                    <li>MikroTik · GPON · live monitoring</li>
                    <li>Support tickets · SMS · customer portal</li>
                    <li>Accounting · HR · resellers · BTRC</li>
                </ul>
            </div>
            <p class="isp-auth-split__footer text-xs text-white/40">Secure admin access</p>
        </aside>

        <main class="isp-auth-split__form">
            <div class="w-full max-w-md">
                {{ $slot }}
            </div>
        </main>
    </div>
</x-filament-panels::layout.base>
