<x-filament-panels::page>
    @php
        $statement = $this->getStatement();
        $summary = $statement['summary'];
        $staff = $statement['staff'];
    @endphp

    <div class="space-y-6">
        {{-- Date filters --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Date From</label>
                        <input type="date" wire:model.live="dateFrom" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">Date To</label>
                        <input type="date" wire:model.live="dateTo" class="fi-input block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500" />
                    </div>
                    <div class="flex gap-2">
                        <button type="button" wire:click="setDatePreset('today')" @class([
                            'px-3 py-2 text-sm font-medium rounded-lg transition',
                            'bg-primary-600 text-white' => $this->activeDatePreset() === 'today',
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' => $this->activeDatePreset() !== 'today',
                        ])>Today</button>
                        <button type="button" wire:click="setDatePreset('yesterday')" @class([
                            'px-3 py-2 text-sm font-medium rounded-lg transition',
                            'bg-primary-600 text-white' => $this->activeDatePreset() === 'yesterday',
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' => $this->activeDatePreset() !== 'yesterday',
                        ])>Yesterday</button>
                        <button type="button" wire:click="setDatePreset('week')" @class([
                            'px-3 py-2 text-sm font-medium rounded-lg transition',
                            'bg-primary-600 text-white' => $this->activeDatePreset() === 'week',
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' => $this->activeDatePreset() !== 'week',
                        ])>This Week</button>
                        <button type="button" wire:click="setDatePreset('month')" @class([
                            'px-3 py-2 text-sm font-medium rounded-lg transition',
                            'bg-primary-600 text-white' => $this->activeDatePreset() === 'month',
                            'bg-gray-100 text-gray-700 hover:bg-gray-200 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600' => $this->activeDatePreset() !== 'month',
                        ])>This Month</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary cards --}}
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-primary-50 dark:bg-primary-500/10">
                        <svg class="h-6 w-6 text-primary-600 dark:text-primary-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Total Collected</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['total_collected'], 0) }} <span class="text-sm font-normal text-gray-500">BDT</span></p>
                    </div>
                </div>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-amber-50 dark:bg-amber-500/10">
                        <svg class="h-6 w-6 text-amber-600 dark:text-amber-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Balance Due</p>
                        <p class="text-2xl font-bold text-amber-700 dark:text-amber-400">{{ number_format($summary['total_balance_due'], 0) }} <span class="text-sm font-normal text-gray-500">BDT</span></p>
                    </div>
                </div>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-teal-50 dark:bg-teal-500/10">
                        <svg class="h-6 w-6 text-teal-600 dark:text-teal-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 002.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 00-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 00.75-.75 2.25 2.25 0 00-.1-.664m-5.8 0A2.251 2.251 0 0113.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25zM6.75 12h.008v.008H6.75V12zm0 3h.008v.008H6.75V15zm0 3h.008v.008H6.75V18z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Transactions</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ number_format($summary['total_transactions']) }}</p>
                    </div>
                </div>
            </div>

            <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 p-6">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 items-center justify-center rounded-lg bg-indigo-50 dark:bg-indigo-500/10">
                        <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-600 dark:text-gray-400">Staff Members</p>
                        <p class="text-2xl font-bold text-gray-900 dark:text-white">{{ $summary['staff_count'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- Staff table --}}
        <div class="fi-section rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="border-b border-gray-200 dark:border-gray-700 p-6">
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Staff Collection Details</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Breakdown of collections by staff member</p>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead class="border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">Staff Name</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 text-right">Collected Amount</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 text-center">Transactions</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400 text-right">Balance Due</th>
                            <th class="px-6 py-3 text-xs font-semibold uppercase tracking-wider text-gray-600 dark:text-gray-400">Last Collection</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse($staff as $member)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50 transition">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-primary-100 dark:bg-primary-500/20 text-primary-700 dark:text-primary-400 font-semibold">
                                            {{ strtoupper(substr($member['name'], 0, 2)) }}
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $member['name'] }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Staff ID: {{ $member['id'] }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <p class="text-lg font-semibold text-primary-600 dark:text-primary-400">{{ number_format($member['collected_amount'], 2) }}</p>
                                    <p class="text-xs text-gray-500">BDT</p>
                                </td>
                                <td class="px-6 py-4 text-center">
                                    <span class="inline-flex items-center rounded-full bg-teal-50 dark:bg-teal-500/10 px-3 py-1 text-sm font-medium text-teal-700 dark:text-teal-400">
                                        {{ number_format($member['transaction_count']) }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    @if ($member['balance_due'] > 0)
                                        <p class="text-lg font-semibold text-amber-700 dark:text-amber-400">{{ number_format($member['balance_due'], 2) }}</p>
                                        <p class="text-xs text-gray-500">BDT</p>
                                    @else
                                        <p class="text-sm text-gray-400 dark:text-gray-500">—</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-sm text-gray-600 dark:text-gray-400">{{ $member['last_collection'] ?? '—' }}</p>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-12 text-center">
                                    <div class="flex flex-col items-center gap-2">
                                        <svg class="h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z" />
                                        </svg>
                                        <p class="text-gray-500 dark:text-gray-400">No collections found for this date range</p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (!empty($staff))
                <div class="border-t border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/50 p-6">
                    <div class="flex items-center justify-between">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Showing <span class="font-semibold text-gray-900 dark:text-white">{{ count($staff) }}</span> staff member{{ count($staff) === 1 ? '' : 's' }}
                        </p>
                        <div class="text-sm text-gray-600 dark:text-gray-400">
                            Report generated on {{ now()->format('d M Y, h:i A') }}
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Help text --}}
        <div class="rounded-lg border border-primary-200 dark:border-primary-500/30 bg-primary-50 dark:bg-primary-500/10 p-4">
            <div class="flex gap-3">
                <svg class="h-5 w-5 text-primary-600 dark:text-primary-400 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                </svg>
                <div class="text-sm text-primary-900 dark:text-primary-100">
                    <p class="font-semibold mb-1">About This Report</p>
                    <ul class="list-disc list-inside space-y-1 text-primary-800 dark:text-primary-200">
                        <li><strong>Collected Amount:</strong> Total money collected by each staff member during the selected date range</li>
                        <li><strong>Balance Due:</strong> Current outstanding cash that the staff member needs to settle (real-time, not date-filtered)</li>
                        <li><strong>Transactions:</strong> Number of payment collections made by the staff member</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
