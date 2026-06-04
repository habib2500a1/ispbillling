<x-filament-panels::page>
    <div class="space-y-4">
        @if ($revealedHmacSecret)
            <div class="rounded-xl border-2 border-amber-400 bg-amber-50 p-4 dark:border-amber-600 dark:bg-amber-950/40">
                <p class="text-sm font-bold text-amber-900 dark:text-amber-100">HMAC secret — copy now</p>
                <p class="mt-1 break-all font-mono text-xs text-amber-950 dark:text-amber-50">{{ $revealedHmacSecret }}</p>
                <p class="mt-2 text-xs text-amber-800 dark:text-amber-200">This box clears when you leave the page. Do not share in chat/email.</p>
            </div>
        @endif

        @if ($revealedSanctumToken)
            <div class="rounded-xl border-2 border-emerald-400 bg-emerald-50 p-4 dark:border-emerald-600 dark:bg-emerald-950/40">
                <p class="text-sm font-bold text-emerald-900 dark:text-emerald-100">REST API token — copy now</p>
                <p class="mt-1 break-all font-mono text-xs text-emerald-950 dark:text-emerald-50">{{ $revealedSanctumToken }}</p>
                <p class="mt-2 text-xs text-emerald-800 dark:text-emerald-200">Authorization: Bearer &lt;token&gt; on /api/v1/*</p>
            </div>
        @endif

        @unless (app(\App\Services\Integrations\TenantApiSettingsService::class)->hasWebhookHmacSecret())
            <div class="rounded-xl border border-dashed border-amber-300 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-700 dark:bg-amber-950/30 dark:text-amber-100">
                <strong>Tip:</strong> Save tenant settings, then click <strong>Regenerate HMAC</strong> before connecting external systems (PBX, billing bridge). Until then, webhooks may use <code class="text-xs">X-ISP-Webhook-Secret</code> from <code class="text-xs">.env</code>.
            </div>
        @endunless
    </div>

    <x-filament-panels::form wire:submit="save">
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>
</x-filament-panels::page>
