@php
    $report = $this->getReportData();
    $pl = $report['pl'];
    $vat = $report['vat'];
    $cb = $report['cashbook'];
    $snap = $report['snapshot'];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit.prevent class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            {{ $this->form }}
        </form>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Profit & loss</h3>
                <p class="text-sm text-gray-500">{{ $report['from']->format('d M Y') }} – {{ $report['to']->format('d M Y') }}</p>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Total income</dt><dd class="font-medium text-emerald-600">{{ number_format($pl['income'], 2) }} BDT</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Total expenses</dt><dd class="font-medium text-rose-600">{{ number_format($pl['expenses'], 2) }} BDT</dd></div>
                    <div class="flex justify-between border-t pt-2 dark:border-gray-700"><dt class="font-semibold">Net profit</dt><dd class="text-lg font-bold">{{ number_format($pl['net_profit'], 2) }} BDT</dd></div>
                </dl>
                @if(count($pl['lines']))
                    <table class="mt-4 w-full text-left text-sm">
                        <thead><tr class="border-b dark:border-gray-700"><th class="py-2">Code</th><th>Account</th><th class="text-right">Amount</th></tr></thead>
                        <tbody>
                        @foreach($pl['lines'] as $line)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 font-mono text-xs">{{ $line['code'] }}</td>
                                <td>{{ $line['name'] }}</td>
                                <td class="text-right">{{ number_format($line['amount'], 2) }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif
            </div>

            <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">VAT report</h3>
                <dl class="mt-4 space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-gray-500">Output VAT (invoices)</dt><dd>{{ number_format($vat['output_vat'], 2) }} BDT</dd></div>
                    <div class="flex justify-between"><dt class="text-gray-500">Input VAT (vendor payments)</dt><dd>{{ number_format($vat['input_vat'], 2) }} BDT</dd></div>
                    <div class="flex justify-between border-t pt-2 font-semibold dark:border-gray-700"><dt>Net VAT payable</dt><dd>{{ number_format($vat['net_vat_payable'], 2) }} BDT</dd></div>
                </dl>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-6 dark:border-gray-700 dark:bg-gray-900">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Cashbook summary</h3>
            <div class="mt-4 grid gap-4 sm:grid-cols-4 text-sm">
                <div><p class="text-gray-500">Opening</p><p class="text-xl font-bold">{{ number_format($cb['opening'], 2) }}</p></div>
                <div><p class="text-gray-500">Receipts</p><p class="text-xl font-bold text-emerald-600">{{ number_format($cb['receipts'], 2) }}</p></div>
                <div><p class="text-gray-500">Payments</p><p class="text-xl font-bold text-rose-600">{{ number_format($cb['payments'], 2) }}</p></div>
                <div><p class="text-gray-500">Closing</p><p class="text-xl font-bold">{{ number_format($cb['closing'], 2) }}</p></div>
            </div>
            <p class="mt-4 text-sm text-gray-500">Customer collections (billing): {{ number_format($snap['collections'], 2) }} BDT</p>
        </div>
    </div>
</x-filament-panels::page>
