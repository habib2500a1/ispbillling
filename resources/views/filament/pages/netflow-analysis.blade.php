@php $report = $this->getReport(); @endphp

<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit.prevent="submit" class="max-w-xs">
            {{ $this->form }}
        </form>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-xl border border-sky-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-sky-600">Flows</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($report['flow_count']) }}</p>
            </div>
            <div class="rounded-xl border border-violet-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-violet-600">Bytes</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($report['total_bytes']) }}</p>
            </div>
            <div class="rounded-xl border border-emerald-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-emerald-600">Packets</p>
                <p class="mt-1 text-2xl font-bold">{{ number_format($report['total_packets']) }}</p>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-semibold">Top sources</h3>
                @forelse($report['top_sources'] as $row)
                    <div class="flex justify-between border-b border-gray-100 py-2 text-sm dark:border-gray-800">
                        <span class="font-mono">{{ $row['ip'] }}</span>
                        <span>{{ $row['bytes_human'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No flow data in this period.</p>
                @endforelse
            </div>
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-semibold">Top destinations</h3>
                @forelse($report['top_destinations'] as $row)
                    <div class="flex justify-between border-b border-gray-100 py-2 text-sm dark:border-gray-800">
                        <span class="font-mono">{{ $row['ip'] }}</span>
                        <span>{{ $row['bytes_human'] }}</span>
                    </div>
                @empty
                    <p class="text-sm text-gray-500">No flow data.</p>
                @endforelse
            </div>
        </div>

        @if(count($report['subscriber_usage']) > 0)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <h3 class="mb-3 font-semibold">Subscriber usage (matched by framed IP)</h3>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="border-b text-xs uppercase text-gray-500">
                                <th class="py-2">Customer</th>
                                <th>IP</th>
                                <th>Bytes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($report['subscriber_usage'] as $row)
                                <tr class="border-b border-gray-100 dark:border-gray-800">
                                    <td class="py-2">{{ $row['customer_name'] ?? '—' }}</td>
                                    <td class="font-mono">{{ $row['ip'] }}</td>
                                    <td>{{ $row['bytes_human'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
