<x-filament-widgets::widget>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-700 dark:bg-gray-900" wire:poll.120s>
        <header class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-semibold text-gray-900 dark:text-white">MikroTik fleet health</h2>
                <p class="text-sm text-gray-500">{{ $online }}/{{ $total }} online · {{ $offline }} offline</p>
            </div>
            <a href="{{ \App\Filament\Resources\MikrotikServerResource::getUrl('index') }}" class="text-sm font-medium text-primary-600 hover:underline">Manage routers</a>
        </header>
        @if (count($servers) === 0)
            <p class="mt-4 text-sm text-gray-500">No enabled MikroTik servers.</p>
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500">
                        <tr>
                            <th class="pb-2 pr-4">Router</th>
                            <th class="pb-2 pr-4">Host</th>
                            <th class="pb-2 pr-4">Status</th>
                            <th class="pb-2 pr-4">Last check</th>
                            <th class="pb-2">Subscribers</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($servers as $s)
                            <tr>
                                <td class="py-2 pr-4 font-medium">{{ $s['name'] }}</td>
                                <td class="py-2 pr-4 font-mono text-xs">{{ $s['host'] }}</td>
                                <td class="py-2 pr-4">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ ($s['status'] ?? '') === 'online' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800' }}">
                                        {{ $s['status'] ?? 'unknown' }}
                                    </span>
                                </td>
                                <td class="py-2 pr-4 text-xs text-gray-500">{{ $s['last_checked_at'] ? \Carbon\Carbon::parse($s['last_checked_at'])->diffForHumans() : '—' }}</td>
                                <td class="py-2">{{ $s['subscribers'] ?? 0 }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</x-filament-widgets::widget>
