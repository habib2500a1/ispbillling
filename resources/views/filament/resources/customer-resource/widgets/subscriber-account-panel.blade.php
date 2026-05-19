@php
    $c = $customer ?? null;
@endphp
<x-filament-widgets::widget>
    @if ($c)
        <div class="rounded-2xl border border-gray-200 bg-gradient-to-br from-slate-50 via-white to-teal-50/30 p-5 dark:border-gray-700 dark:from-gray-900 dark:via-gray-900 dark:to-teal-950/20">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500">Subscriber panel</h3>
                    <p class="mt-1 text-lg font-bold text-gray-900 dark:text-white">{{ $c->name }} <span class="font-mono text-sm text-gray-500">({{ $c->customer_code }})</span></p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ $collect_url }}" class="rounded-lg bg-teal-600 px-3 py-2 text-xs font-bold text-white hover:bg-teal-700">Collect payment</a>
                    <a href="{{ $edit_url }}" class="rounded-lg border border-gray-300 px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">Edit subscriber</a>
                </div>
            </div>

            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl border bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs uppercase text-gray-500">কখন off হবে</p>
                    @if ($c->service_expires_at)
                        <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            শেষ বৈধ: <span class="text-teal-700 dark:text-teal-400">{{ $c->service_expires_at->format('d M Y') }}</span>
                        </p>
                        <p class="text-xs text-rose-600 dark:text-rose-400">
                            লাইন বন্ধ: {{ $c->serviceOffDate()?->format('d M Y') }}
                            @if ($c->isServiceExpired())
                                (ইতিমধ্যে off)
                            @elseif ($c->daysUntilServiceExpiry() === 0)
                                (আজ শেষ)
                            @else
                                ({{ $c->daysUntilServiceExpiry() }} দিন বাকি)
                            @endif
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500">মেয়াদ সেট নেই</p>
                    @endif
                </div>
                <div class="rounded-xl border bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs uppercase text-gray-500">Network</p>
                    <p class="mt-1 font-semibold capitalize {{ ($c->network_access_state ?? '') === 'suspended' ? 'text-rose-600' : 'text-emerald-600' }}">
                        {{ $c->network_access_state ?? 'active' }}
                    </p>
                    <p class="text-xs text-gray-500">Status: {{ $c->statusLabel() }}</p>
                </div>
                <div class="rounded-xl border bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs uppercase text-gray-500">Open balance</p>
                    <p class="mt-1 text-lg font-bold {{ $open_balance > 0 ? 'text-amber-700' : 'text-gray-900 dark:text-white' }}">
                        {{ number_format($open_balance, 2) }} BDT
                    </p>
                </div>
                <div class="rounded-xl border bg-white p-3 dark:border-gray-700 dark:bg-gray-900">
                    <p class="text-xs uppercase text-gray-500">Package</p>
                    <p class="mt-1 font-semibold">{{ $c->package?->name ?? '—' }}</p>
                    @if ($c->package?->price_monthly)
                        <p class="text-xs text-gray-500">{{ number_format((float) $c->package->price_monthly, 0) }} BDT/mo</p>
                    @endif
                </div>
            </div>

            @if ($recent_payments->isNotEmpty())
                <div class="mt-4 rounded-xl border overflow-hidden dark:border-gray-700">
                    <div class="flex items-center justify-between bg-gray-50 px-3 py-2 dark:bg-gray-800">
                        <span class="text-xs font-semibold uppercase text-gray-500">Recent collections</span>
                        <a href="{{ $collect_url }}" class="text-xs font-semibold text-teal-600 hover:underline">View all in desk →</a>
                    </div>
                    <table class="w-full text-sm">
                        @foreach ($recent_payments as $pay)
                            <tr class="border-t dark:border-gray-800">
                                <td class="px-3 py-2">{{ $pay->paid_at?->format('d M H:i') }}</td>
                                <td class="px-3 py-2">{{ $pay->recorder?->name ?? 'Online' }}</td>
                                <td class="px-3 py-2 text-right font-medium">{{ number_format((float) $pay->amount, 2) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <a href="{{ $collect_url }}&edit_payment={{ $pay->id }}" class="text-xs text-violet-600 hover:underline">Edit</a>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
