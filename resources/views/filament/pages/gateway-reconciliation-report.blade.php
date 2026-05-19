@php $r = $this->getReport(); @endphp
<x-filament-panels::page>
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ \App\Filament\Resources\PendingGatewayPaymentResource::getUrl('index') }}" class="text-sm text-amber-600 hover:underline">Pending payments →</a>
        <div class="ml-auto flex gap-2">
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800" />
            <input type="date" wire:model.live="dateTo" class="rounded-lg border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-4">
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Completed payments</p>
            <p class="text-xl font-bold">{{ $r['payment_count'] }}</p>
            <p class="text-sm text-gray-500">{{ number_format($r['payment_total'], 2) }} BDT</p>
        </div>
        <div class="rounded-xl border bg-amber-50 p-4 dark:border-amber-900/40 dark:bg-amber-950/30">
            <p class="text-xs uppercase text-amber-800 dark:text-amber-200">Pending approval</p>
            <p class="text-xl font-bold text-amber-900 dark:text-amber-100">{{ $r['pending_count'] }}</p>
        </div>
        <div class="rounded-xl border bg-rose-50 p-4 dark:border-rose-900/40 dark:bg-rose-950/30">
            <p class="text-xs uppercase text-rose-800 dark:text-rose-200">Stale pending (24h+)</p>
            <p class="text-xl font-bold text-rose-900 dark:text-rose-100">{{ $r['stale_pending_count'] }}</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Duplicate TrxID</p>
            <p class="text-xl font-bold">{{ $r['duplicate_transactions']->count() }}</p>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">By gateway</h3>
            <table class="w-full text-sm">
                @forelse ($r['by_gateway'] as $gw => $row)
                    <tr class="border-t dark:border-gray-800">
                        <td class="px-4 py-2 capitalize">{{ $gw }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($row['total'], 2) }}</td>
                        <td class="px-4 py-2 text-right text-gray-500">{{ $row['count'] }} ({{ $row['with_trx'] }} trx)</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No payments</td></tr>
                @endforelse
            </table>
        </div>
        <div class="rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Pending queue</h3>
            <table class="w-full text-sm">
                @forelse ($r['pending'] as $p)
                    <tr class="border-t dark:border-gray-800">
                        <td class="px-4 py-2 font-mono text-xs">{{ $p->transaction_id }}</td>
                        <td class="px-4 py-2">{{ $p->customer?->name }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $p->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No pending</td></tr>
                @endforelse
            </table>
        </div>
    </div>
</x-filament-panels::page>
