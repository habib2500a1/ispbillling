@php
    $report = $this->getReport();
    $stages = $report['stages'] ?? [];
@endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Dunning</p>
                <p class="mt-1 text-lg font-semibold {{ ($report['enabled'] ?? false) ? 'text-emerald-600' : 'text-rose-600' }}">
                    {{ ($report['enabled'] ?? false) ? 'Enabled' : 'Disabled' }}
                </p>
            </motion.div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Eligible today (all stages)</p>
                <p class="mt-1 text-2xl font-bold">{{ $report['total_eligible'] ?? 0 }}</p>
            </motion.div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-medium uppercase text-gray-500">Sent (24h)</p>
                <p class="mt-1 text-2xl font-bold">{{ $report['total_sent_24h'] ?? 0 }}</p>
                <p class="mt-1 text-xs text-gray-500">Payment links: {{ ($report['payment_links'] ?? false) ? 'Yes' : 'No' }}</p>
            </motion.div>
        </motion.div>

        <div class="overflow-hidden rounded-xl border border-gray-200 dark:border-gray-700">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs uppercase text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3">Stage</th>
                        <th class="px-4 py-3">Offset (days)</th>
                        <th class="px-4 py-3">Eligible invoices</th>
                        <th class="px-4 py-3">Sent (24h)</th>
                        <th class="px-4 py-3">Links</th>
                        <th class="px-4 py-3">Clicks</th>
                        <th class="px-4 py-3">Paid via link</th>
                        <th class="px-4 py-3">Channels</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($stages as $row)
                        <tr class="bg-white dark:bg-gray-900">
                            <td class="px-4 py-3 font-medium">{{ $row['label'] }}</td>
                            <td class="px-4 py-3">{{ $row['offset_days'] }}</td>
                            <td class="px-4 py-3">{{ $row['eligible'] }}</td>
                            <td class="px-4 py-3">{{ $row['sent_24h'] }}</td>
                            <td class="px-4 py-3">{{ $row['links_24h'] ?? 0 }}</td>
                            <td class="px-4 py-3">{{ $row['clicks_24h'] ?? 0 }}</td>
                            <td class="px-4 py-3">{{ $row['converted_24h'] ?? 0 }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ implode(', ', $row['channels'] ?? []) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-gray-500">No dunning stages configured.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </motion.div>

        <p class="text-xs text-gray-500">
            Cron: <code>isp:send-invoice-due-reminders</code> (Automatic process). Configure stages in <code>config/billing.php</code> and event toggles in Notification settings.
        </p>
    </motion.div>
</x-filament-panels::page>
