<div class="isp-auth-card isp-auth-card--login">
    <h2 class="isp-auth-card__title">Login to your account</h2>
    <p class="isp-auth-card__sub">Admin panel · {{ config('isp.company_name') }}</p>

    <div
        id="isp-livewire-blocked-notice"
        class="mb-4 hidden rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"
        role="alert"
    >
        <strong>Login scripts blocked.</strong>
        Cloudflare Rocket Loader বা ad-blocker Livewire বন্ধ করেছে।
        Cloudflare → Speed → Rocket Loader → Off (অথবা <code>/admin/*</code> exclude), তারপর hard refresh (Ctrl+F5)।
    </div>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_BEFORE, scopes: $this->getRenderHookScopes()) }}

    <x-filament-panels::form id="form" wire:submit="authenticate" class="mt-8">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, scopes: $this->getRenderHookScopes()) }}
</div>

<script data-cfasync="false">
(function () {
    function blockNativeLoginSubmit() {
        var form = document.getElementById('form');
        if (!form || form.dataset.ispSubmitGuard === '1') {
            return;
        }
        form.dataset.ispSubmitGuard = '1';
        form.addEventListener('submit', function (e) {
            if (typeof window.Livewire === 'undefined') {
                e.preventDefault();
                e.stopPropagation();
                var notice = document.getElementById('isp-livewire-blocked-notice');
                if (notice) {
                    notice.classList.remove('hidden');
                }
            }
        }, true);
    }

    function checkLivewireLoaded() {
        if (typeof window.Livewire !== 'undefined') {
            return;
        }
        var notice = document.getElementById('isp-livewire-blocked-notice');
        if (notice) {
            notice.classList.remove('hidden');
        }
        blockNativeLoginSubmit();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            blockNativeLoginSubmit();
            setTimeout(checkLivewireLoaded, 2500);
        });
    } else {
        blockNativeLoginSubmit();
        setTimeout(checkLivewireLoaded, 2500);
    }
})();
</script>
