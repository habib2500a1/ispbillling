@php
    $gateways = \App\Support\PaymentGateway::webhookGateways();
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-2xl border border-primary-200 bg-gradient-to-br from-primary-50 to-white p-6 dark:border-primary-900/40 dark:from-primary-950/40 dark:to-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Payment system</h2>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                Manual entry · partial pay · wallet · refunds · multi-gateway webhooks · PDF receipts.
            </p>
            <div class="mt-4 flex flex-wrap gap-2">
                @foreach ($gateways as $gw)
                    @php $on = (bool) config("payments.gateways.{$gw}.enabled", false) || ($gw === 'bkash' && config('bkash.enabled')); @endphp
                    <span class="rounded-full px-3 py-1 text-xs font-medium {{ $on ? 'bg-success-100 text-success-800 dark:bg-success-500/20 dark:text-success-300' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400' }}">
                        {{ \App\Support\PaymentGateway::label($gw) }}{{ $on ? ' ✓' : '' }}
                    </span>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ \App\Filament\Pages\ManagePaymentSettings::getUrl() }}" class="rounded-xl border border-primary-200 bg-primary-50 p-5 shadow-sm transition hover:border-primary-400 dark:border-primary-900/50 dark:bg-primary-950/30">
                <p class="font-semibold text-primary-900 dark:text-primary-100">Payment gateway settings</p>
                <p class="mt-1 text-sm text-primary-800/80 dark:text-primary-200/80">bKash sandbox/live, credentials, channels, test connection.</p>
            </a>
            <a href="{{ route('bill-payment.index') }}" target="_blank" class="rounded-xl border border-teal-200 bg-teal-50 p-5 shadow-sm transition hover:border-teal-400 dark:border-teal-900/50 dark:bg-teal-950/30">
                <p class="font-semibold text-teal-900 dark:text-teal-100">Public bill payment</p>
                <p class="mt-1 text-sm text-teal-800/80 dark:text-teal-200/80">Client code → OTP → pay (outside login).</p>
            </a>
            <a href="{{ \App\Filament\Resources\PaymentResource::getUrl('create') }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">Manual payment</p>
                <p class="mt-1 text-sm text-gray-500">Cash, bank, or mark gateway payment received.</p>
            </a>
            <a href="{{ \App\Filament\Resources\PaymentResource::getUrl() }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">All payments</p>
                <p class="mt-1 text-sm text-gray-500">Receipts, refunds, filters by gateway.</p>
            </a>
            <a href="{{ \App\Filament\Resources\CustomerResource::getUrl() }}" class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-primary-400 dark:border-gray-700 dark:bg-gray-900">
                <p class="font-semibold text-gray-900 dark:text-white">Wallet balance</p>
                <p class="mt-1 text-sm text-gray-500">Subscriber account_balance &amp; pay-from-wallet.</p>
            </a>
        </div>

        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm dark:border-amber-900/50 dark:bg-amber-950/30">
            <p class="font-semibold text-amber-950 dark:text-amber-100">Webhook URL (auto payment detection)</p>
            <p class="mt-2 font-mono text-xs text-amber-900 dark:text-amber-200">
                POST {{ url('/api/webhooks/payments/{gateway}') }}<br>
                Headers: X-Webhook-Secret · Body: transaction_id, amount, customer_id|phone, invoice_id (optional)
            </p>
            <p class="mt-2 text-xs text-amber-800 dark:text-amber-300">Gateways: nagad, rocket, sslcommerz, stripe, paypal, bkash</p>
        </div>
    </div>
</x-filament-panels::page>
