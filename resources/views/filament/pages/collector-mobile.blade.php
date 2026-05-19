@php
    $summary = $this->getTodaySummary();
    $recent = $this->getRecentCollections();
    $canPick = $this->canPickCollector();
    $staffOptions = $this->getCollectorStaffOptions();
@endphp

<x-filament-panels::page>
    <div class="mx-auto max-w-lg space-y-4">
        <div class="rounded-xl border border-teal-200 bg-teal-50/60 p-4 dark:border-teal-900/40 dark:bg-teal-950/30">
            <p class="text-sm text-teal-950 dark:text-teal-100">
                @if ($canPick)
                    <strong>Admin collection:</strong> choose which staff member gets credit, or collect under your own name.
                @else
                    Collections are recorded under <strong>{{ auth()->user()?->name }}</strong>. Admin can see all staff totals in settlement.
                @endif
            </p>
        </div>

        <div class="grid grid-cols-2 gap-2">
            <button type="button" wire:click="$set('panelTab', 'collect')"
                class="rounded-lg px-3 py-2 text-sm font-semibold {{ $panelTab === 'collect' ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                Collect bill
            </button>
            <button type="button" wire:click="$set('panelTab', 'activity')"
                class="rounded-lg px-3 py-2 text-sm font-semibold {{ $panelTab === 'activity' ? 'bg-teal-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-gray-800 dark:text-gray-300' }}">
                Today's activity
            </button>
        </div>

        @if ($panelTab === 'activity')
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-xs font-bold uppercase text-gray-500">Today ({{ now()->format('d M Y') }})</p>
                <p class="mt-1 text-2xl font-bold tabular-nums text-teal-700 dark:text-teal-300">
                    {{ number_format($summary['total'], 0) }} <span class="text-sm font-semibold">BDT</span>
                </p>
                @if ($canPick && count($summary['by_collector'] ?? []) > 0)
                    <ul class="mt-3 space-y-2 border-t pt-3 dark:border-gray-800">
                        @foreach ($summary['by_collector'] as $row)
                            <li class="flex justify-between text-sm">
                                <span class="font-medium">{{ $row['name'] }}</span>
                                <span class="tabular-nums text-gray-600 dark:text-gray-400">{{ number_format($row['total'], 0) }} BDT</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <a href="{{ \App\Filament\Pages\CollectorCashHub::getUrl() }}" class="mt-3 inline-block text-xs font-semibold text-teal-600 hover:underline">
                    Full settlement & dues →
                </a>
            </div>

            <div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
                <p class="border-b px-4 py-2 text-xs font-bold uppercase text-gray-500 dark:border-gray-800">Recent collections</p>
                <ul class="max-h-80 divide-y overflow-y-auto text-sm dark:divide-gray-800">
                    @forelse ($recent as $item)
                        <li class="px-4 py-3">
                            <div class="flex justify-between gap-2">
                                <span class="font-semibold">{{ $item['customer_name'] }}</span>
                                <span class="shrink-0 font-bold tabular-nums text-teal-700 dark:text-teal-300">{{ number_format($item['amount'], 0) }}</span>
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500">
                                Collector: <strong>{{ $item['collector_name'] }}</strong>
                                @if ($item['entered_by_name'])
                                    · Entered by {{ $item['entered_by_name'] }}
                                @endif
                            </p>
                            <p class="text-xs text-gray-400">
                                {{ $item['collected_at'] }} · {{ $item['receipt'] ?? '—' }} · {{ strtoupper($item['method'] ?? '') }}
                            </p>
                        </li>
                    @empty
                        <li class="px-4 py-6 text-center text-gray-500">No collections yet today.</li>
                    @endforelse
                </ul>
            </div>
        @else
            @if ($selectedCustomerId === null)
                @if ($canPick && count($staffOptions) > 0)
                    <div class="rounded-xl border border-violet-200 bg-violet-50/50 p-4 dark:border-violet-900/40 dark:bg-violet-950/20">
                        <label class="mb-1 block text-xs font-bold uppercase text-violet-800 dark:text-violet-200">Collection credited to</label>
                        <select wire:model.live="collectorUserId" class="w-full rounded-lg border border-violet-200 px-3 py-2 text-sm dark:border-violet-800 dark:bg-gray-900">
                            @foreach ($staffOptions as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif

                <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    <label class="mb-1 block text-xs font-bold uppercase text-gray-500">Search subscriber</label>
                    <input type="search" wire:model.live.debounce.400ms="search" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800" placeholder="Code, phone, name…" />
                    <ul class="mt-2 max-h-64 overflow-y-auto text-sm">
                        @forelse ($results as $row)
                            <li>
                                <button type="button" wire:click="selectCustomer({{ $row['id'] }})" class="w-full rounded-lg px-2 py-2 text-left hover:bg-gray-100 dark:hover:bg-gray-800">
                                    <span class="font-semibold">{{ $row['name'] }}</span>
                                    <span class="text-gray-500">· {{ $row['customer_code'] ?? $row['id'] }}</span>
                                    @if (($row['balance_due'] ?? 0) > 0)
                                        <span class="block text-xs text-teal-600">{{ number_format($row['balance_due'], 2) }} BDT due</span>
                                    @endif
                                </button>
                            </li>
                        @empty
                            @if (strlen($search) >= 2)
                                <li class="px-2 py-2 text-gray-500">No matches</li>
                            @endif
                        @endforelse
                    </ul>
                </div>
            @else
                <form wire:submit="collectPayment" class="space-y-3 rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                    @if ($canPick && count($staffOptions) > 0)
                        <div>
                            <label class="text-xs font-bold uppercase text-gray-500">Credited to staff</label>
                            <select wire:model="collectorUserId" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-800">
                                @foreach ($staffOptions as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <p class="mt-1 text-xs text-gray-500">Admin: {{ auth()->user()?->name }} is entering this payment.</p>
                        </div>
                    @else
                        <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                            Collector: <strong>{{ auth()->user()?->name }}</strong>
                        </p>
                    @endif

                    <div class="flex items-center justify-between">
                        <p class="font-bold">{{ $selectedCustomer['name'] ?? '' }}</p>
                        <button type="button" wire:click="$set('selectedCustomerId', null)" class="text-xs text-gray-500 hover:underline">Change</button>
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-gray-500">Amount (BDT)</label>
                        <input type="number" step="0.01" min="0.01" wire:model="amount" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800" required />
                    </div>
                    <div>
                        <label class="text-xs font-bold uppercase text-gray-500">Method</label>
                        <select wire:model="method" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 dark:border-gray-600 dark:bg-gray-800">
                            @foreach ($this->getMethodOptions() as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <p class="text-xs text-gray-500">
                        GPS:
                        @if ($latitude !== null)
                            {{ number_format($latitude, 5) }}, {{ number_format($longitude, 5) }}
                            @if ($accuracyMeters)(±{{ $accuracyMeters }}m)@endif
                        @else
                            not captured —
                        @endif
                        <button type="button" onclick="captureCollectorGps()" class="text-teal-600 hover:underline">capture</button>
                    </p>
                    <button type="submit" class="w-full rounded-lg bg-teal-600 px-4 py-3 text-sm font-bold text-white hover:bg-teal-700">
                        Collect payment
                    </button>
                </form>
            @endif
        @endif
    </div>

    @script
    <script>
        window.captureCollectorGps = function () {
            if (!navigator.geolocation) return;
            navigator.geolocation.getCurrentPosition((pos) => {
                $wire.setGps(pos.coords.latitude, pos.coords.longitude, Math.round(pos.coords.accuracy));
            }, () => {}, { enableHighAccuracy: true, timeout: 12000 });
        };
        document.addEventListener('livewire:navigated', () => {
            if (@js($selectedCustomerId !== null)) captureCollectorGps();
        });
    </script>
    @endscript
</x-filament-panels::page>
