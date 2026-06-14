<x-filament-panels::page>
    @php
        $run = $this->latestRun();
        $localCounts = $this->localCounts();
        $coverage = $this->mirrorCoverage();
        $fieldSummary = $this->rawFieldSummary();
        $settings = $this->syncSettings();
    @endphp

    <div class="space-y-6">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-slate-950 dark:text-white">Latest raw mirror run</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Raw snapshots are saved before normalization so missing or unmapped source fields can be traced later.
                    </p>
                </div>
                <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold
                    @if ($run['status'] === 'completed') bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300
                    @elseif ($run['status'] === 'failed') bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300
                    @else bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300 @endif">
                    {{ $run['status'] }}
                </span>
            </div>

            <dl class="mt-5 grid gap-4 md:grid-cols-3">
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Run UUID</dt>
                    <dd class="mt-1 break-all text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $run['uuid'] }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Started</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $run['started_at'] ?? 'Not started' }}</dd>
                </div>
                <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-950/60">
                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Finished</dt>
                    <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $run['finished_at'] ?? 'Not finished' }}</dd>
                </div>
            </dl>
        </div>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            @foreach ($localCounts as $item)
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="text-sm text-slate-500 dark:text-slate-400">{{ $item['label'] }}</div>
                    <div class="mt-2 text-2xl font-bold text-slate-950 dark:text-white">{{ number_format((int) $item['value']) }}</div>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Sync settings</h3>
                <div class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($settings as $item)
                        <div class="flex items-center justify-between gap-4 py-3 text-sm">
                            <span class="text-slate-500 dark:text-slate-400">{{ $item['label'] }}</span>
                            <span class="font-semibold text-slate-900 dark:text-slate-100">{{ $item['value'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <h3 class="text-base font-semibold text-slate-950 dark:text-white">Raw mirror coverage</h3>
                <div class="mt-4 overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="text-xs uppercase tracking-wide text-slate-500">
                            <tr>
                                <th class="py-2 pr-4">Domain</th>
                                <th class="py-2 pr-4">Records</th>
                                <th class="py-2">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($coverage as $row)
                                <tr>
                                    <td class="py-2 pr-4 font-medium text-slate-900 dark:text-slate-100">{{ $row['domain'] }}</td>
                                    <td class="py-2 pr-4 text-slate-600 dark:text-slate-300">{{ number_format($row['records']) }}</td>
                                    <td class="py-2">
                                        <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $row['records'] > 0 ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300' : 'bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300' }}">
                                            {{ $row['status'] }}
                                        </span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <h3 class="text-base font-semibold text-slate-950 dark:text-white">Raw source keys / unmapped field clues</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                Use this to spot fields that are present in the old portal response but not yet normalized into local tables.
            </p>
            <div class="mt-4 space-y-3">
                @forelse ($fieldSummary as $row)
                    <div class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-950/60">
                        <div class="font-semibold text-slate-900 dark:text-slate-100">{{ $row['domain'] }}</div>
                        <div class="mt-1 break-words text-slate-600 dark:text-slate-300">{{ $row['keys'] }}</div>
                    </div>
                @empty
                    <div class="rounded-xl bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-500/10 dark:text-amber-200">
                        No raw JSON field summary yet. Run <code>php artisan isp:mirror-legacy-portal --with-customer-details --with-history</code>.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
