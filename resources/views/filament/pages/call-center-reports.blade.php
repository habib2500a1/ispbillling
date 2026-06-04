@php
    $totals = $this->totals;
    $rows = $this->rows;
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6">
        <x-isp.hub-hero
            eyebrow="Call center"
            title="Call reports"
            description="Staff performance by call logs — Sheba-Fi call_reports parity."
            class="isp-hub-hero--amber"
        />

        <div class="flex flex-wrap items-end gap-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <div>
                <label class="text-xs font-semibold text-gray-500">From</label>
                <input type="date" wire:model.live="dateFrom" class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
            </div>
            <div>
                <label class="text-xs font-semibold text-gray-500">To</label>
                <input type="date" wire:model.live="dateTo" class="mt-1 block rounded-lg border-gray-300 text-sm dark:border-gray-600 dark:bg-gray-800" />
            </div>
            <div class="flex flex-wrap gap-2 pb-0.5">
                <x-filament::button size="sm" color="gray" wire:click="setDatePreset('today')">Today</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="setDatePreset('week')">This week</x-filament::button>
                <x-filament::button size="sm" color="gray" wire:click="setDatePreset('month')">This month</x-filament::button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Total calls</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($totals['total_calls']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Answered</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600">{{ number_format($totals['answered']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Missed</p>
                <p class="mt-1 text-2xl font-bold text-rose-600">{{ number_format($totals['missed']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Inbound</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($totals['inbound']) }}</p>
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Outbound</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($totals['outbound']) }}</p>
            </div>
        </div>

        <section class="isp-reports-table-card rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-gray-700">
                <h3 class="text-sm font-bold text-gray-900 dark:text-white">Staff summary</h3>
            </div>
            @if (count($rows) === 0)
                <p class="p-6 text-sm text-gray-500">No call logs in this period.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800">
                            <tr>
                                <th class="px-4 py-2">Staff</th>
                                <th class="px-4 py-2">Total</th>
                                <th class="px-4 py-2">Inbound</th>
                                <th class="px-4 py-2">Outbound</th>
                                <th class="px-4 py-2">Answered</th>
                                <th class="px-4 py-2">Missed</th>
                                <th class="px-4 py-2">Avg duration</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($rows as $row)
                                <tr>
                                    <td class="px-4 py-2 font-medium">{{ $row['staff_name'] }}</td>
                                    <td class="px-4 py-2">{{ number_format($row['total']) }}</td>
                                    <td class="px-4 py-2">{{ number_format($row['inbound']) }}</td>
                                    <td class="px-4 py-2">{{ number_format($row['outbound']) }}</td>
                                    <td class="px-4 py-2 text-emerald-600">{{ number_format($row['answered']) }}</td>
                                    <td class="px-4 py-2 text-rose-600">{{ number_format($row['missed']) }}</td>
                                    <td class="px-4 py-2">{{ gmdate('i:s', max(0, $row['avg_duration_seconds'])) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-filament-panels::page>
