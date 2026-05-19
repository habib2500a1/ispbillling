@php $r = $this->getReport(); @endphp
<x-filament-panels::page>
    <div class="mb-4 flex flex-wrap items-end gap-3">
        <a href="{{ \App\Filament\Pages\BillCollectionDesk::getUrl() }}" class="text-sm text-primary-600 hover:underline">← Collection desk</a>
        <a href="{{ \App\Filament\Pages\CollectorMobile::getUrl() }}" class="text-sm text-teal-600 hover:underline">Collector mobile →</a>
        <div class="ml-auto flex flex-wrap gap-2">
            <input type="date" wire:model.live="dateFrom" class="rounded-lg border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800" />
            <input type="date" wire:model.live="dateTo" class="rounded-lg border border-gray-300 px-2 py-1 text-sm dark:border-gray-600 dark:bg-gray-800" />
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Visits</p>
            <p class="text-2xl font-bold">{{ $r['visit_count'] }}</p>
            <p class="text-xs text-gray-500">{{ $r['with_gps'] }} with GPS</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Collected</p>
            <p class="text-2xl font-bold">{{ number_format($r['total_collected'], 2) }} BDT</p>
        </div>
        <div class="rounded-xl border bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-xs uppercase text-gray-500">Period</p>
            <p class="text-sm font-semibold">{{ $r['from'] }} → {{ $r['to'] }}</p>
        </div>
    </div>

    @if (count($r['map_points']) > 0)
        <div class="mt-6 rounded-xl border overflow-hidden dark:border-gray-700" wire:ignore>
            <div id="collector-visits-map" class="h-80 w-full bg-gray-100 dark:bg-gray-800"></div>
            <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
            <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
            <script>
                document.addEventListener('DOMContentLoaded', function () {
                    const points = @json($r['map_points']);
                    if (!points.length || typeof L === 'undefined') return;
                    const map = L.map('collector-visits-map').setView([points[0].lat, points[0].lng], 13);
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);
                    const bounds = [];
                    points.forEach(p => {
                        L.marker([p.lat, p.lng]).addTo(map).bindPopup(p.label);
                        bounds.push([p.lat, p.lng]);
                    });
                    if (bounds.length > 1) map.fitBounds(bounds, { padding: [30, 30] });
                });
            </script>
        </div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl border overflow-hidden dark:border-gray-700">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Collector leaderboard</h3>
            <table class="w-full text-sm">
                @forelse ($r['leaderboard'] as $row)
                    <tr class="border-t dark:border-gray-800">
                        <td class="px-4 py-2">{{ $row['collector'] }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format($row['total'], 2) }} BDT</td>
                        <td class="px-4 py-2 text-right text-gray-500">{{ $row['visits'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="px-4 py-6 text-center text-gray-500">No visits in range</td></tr>
                @endforelse
            </table>
        </div>
        <div class="rounded-xl border overflow-hidden dark:border-gray-700 max-h-96 overflow-y-auto">
            <h3 class="bg-gray-50 px-4 py-2 text-sm font-semibold dark:bg-gray-800">Recent visits</h3>
            <table class="w-full text-sm">
                @foreach ($r['visits']->take(30) as $visit)
                    <tr class="border-t dark:border-gray-800">
                        <td class="px-4 py-2">{{ $visit->visited_at?->format('M d H:i') }}</td>
                        <td class="px-4 py-2">{{ $visit->customer?->name }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) ($visit->amount_collected ?? 0), 0) }}</td>
                        <td class="px-4 py-2 text-xs">
                            @if ($visit->latitude)
                                <a href="https://maps.google.com/?q={{ $visit->latitude }},{{ $visit->longitude }}" target="_blank" class="text-teal-600 hover:underline">Map</a>
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>
</x-filament-panels::page>
