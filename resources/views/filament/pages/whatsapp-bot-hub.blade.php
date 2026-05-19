<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold">Meta Cloud API webhook</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Callback URL (POST + GET verify):
            </p>
            <p class="mt-2 break-all rounded-lg bg-gray-100 p-3 font-mono text-sm dark:bg-gray-800">{{ $this->getWebhookUrl() }}</p>
            <p class="mt-3 text-xs text-gray-500">Verify token: <code>{{ config('whatsapp_bot.verify_token') }}</code></p>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div class="rounded-xl border p-4 {{ $this->isBotEnabled() ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30' : 'border-gray-200 dark:border-gray-700' }}">
                <p class="text-xs font-bold uppercase">Bot</p>
                <p class="mt-1 text-lg font-semibold">{{ $this->isBotEnabled() ? 'Enabled' : 'Disabled' }}</p>
                <p class="text-xs text-gray-500">WHATSAPP_BOT_ENABLED</p>
            </div>
            <div class="rounded-xl border p-4 {{ $this->isWhatsAppConfigured() ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/30' : 'border-gray-200 dark:border-gray-700' }}">
                <p class="text-xs font-bold uppercase">WhatsApp API</p>
                <p class="mt-1 text-lg font-semibold">{{ $this->isWhatsAppConfigured() ? 'Configured' : 'Not configured' }}</p>
                <p class="text-xs text-gray-500">System → Notifications</p>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 text-sm dark:border-gray-700 dark:bg-gray-900">
            <p class="font-semibold">Commands (EN / BN)</p>
            <ul class="mt-2 list-inside list-disc space-y-1 text-gray-600 dark:text-gray-400">
                <li><strong>MENU</strong> — show options</li>
                <li><strong>BALANCE</strong> — wallet balance</li>
                <li><strong>BILL</strong> — latest due invoice + /pay link</li>
                <li><strong>PAY</strong> — payment link for due invoice</li>
                <li><strong>TICKET</strong> — list open tickets</li>
                <li><strong>PACKAGES</strong> — plan list &amp; prices</li>
                <li><strong>SUPPORT &lt;message&gt;</strong> — open support ticket</li>
            </ul>
        </div>
    </div>
</x-filament-panels::page>
