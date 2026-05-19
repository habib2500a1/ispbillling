@php $logs = $this->getPollLogs(); @endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl border border-emerald-200 bg-gradient-to-r from-emerald-50 to-white p-5 dark:border-gray-700 dark:from-emerald-950/30 dark:to-gray-900">
            <h2 class="text-lg font-semibold">SNMP poll history</h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Latest OLT polls (sysDescr, sysUpTime, IF-MIB). Run <code class="text-xs">isp:poll-olt-intelligence</code> or use OLT → SNMP poll.
            </p>
        </div>

        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase text-gray-500 dark:bg-gray-800">
                    <tr>
                        <th class="px-4 py-3">Time</th>
                        <th class="px-4 py-3">OLT</th>
                        <th class="px-4 py-3">OK</th>
                        <th class="px-4 py-3">ONUs</th>
                        <th class="px-4 py-3">IF up/down</th>
                        <th class="px-4 py-3">Error</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($logs as $log)
                        <tr class="border-t border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2 whitespace-nowrap">{{ $log->polled_at?->format('Y-m-d H:i') }}</td>
                            <td class="px-4 py-2">{{ $log->device?->name ?? '—' }}</td>
                            <td class="px-4 py-2">
                                @if($log->success)
                                    <span class="text-emerald-600">Yes</span>
                                @else
                                    <span class="text-rose-600">No</span>
                                @endif
                            </td>
                            <td class="px-4 py-2">{{ $log->onus_online ?? 0 }} / {{ ($log->onus_online ?? 0) + ($log->onus_offline ?? 0) }}</td>
                            <td class="px-4 py-2">{{ $log->interfaces_up ?? 0 }} up / {{ max(0, ($log->interfaces_total ?? 0) - ($log->interfaces_up ?? 0)) }} down</td>
                            <td class="max-w-xs truncate px-4 py-2 text-xs text-gray-500">{{ $log->error_message }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-gray-500">No SNMP polls yet. Configure OLT SNMP community and run a poll.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
