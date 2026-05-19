@php $stats = $this->getSecurityStats(); @endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero title="Security dashboard" description="Login attempts, failed logins, staff activity and audit trail." class="isp-hub-hero--rose" />

        <div class="isp-hub-stat-grid">
            <div class="isp-hub-stat"><span class="isp-hub-stat-label">Logins today</span><strong>{{ $stats['logins_today'] }}</strong></div>
            <div class="isp-hub-stat isp-hub-stat--danger"><span class="isp-hub-stat-label">Failed today</span><strong class="isp-hub-stat-value--danger">{{ $stats['failed_today'] }}</strong></div>
            <div class="isp-hub-stat"><span class="isp-hub-stat-label">Events today</span><strong>{{ $stats['activity_today'] }}</strong></div>
            <div class="isp-hub-stat"><span class="isp-hub-stat-label">Staff</span><strong>{{ $stats['staff_total'] }}</strong><span class="isp-hub-stat-hint">{{ $stats['inactive_staff'] }} inactive</span></div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="isp-module-card p-4">
                <h3 class="font-semibold mb-3">Recent failed logins</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($stats['recent_failed'] as $row)
                        <li class="flex justify-between gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <span>{{ $row['ip_address'] ?? '—' }}</span>
                            <span class="text-gray-500">{{ \Carbon\Carbon::parse($row['created_at'])->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500">No failed attempts logged.</li>
                    @endforelse
                </ul>
            </div>
            <div class="isp-module-card p-4">
                <h3 class="font-semibold mb-3">Recent sign-ins</h3>
                <ul class="space-y-2 text-sm">
                    @forelse ($stats['recent_logins'] as $row)
                        <li class="flex justify-between gap-2 border-b border-gray-100 dark:border-gray-800 pb-2">
                            <span>{{ $row['description'] }}</span>
                            <span class="text-gray-500">{{ \Carbon\Carbon::parse($row['created_at'])->diffForHumans() }}</span>
                        </li>
                    @empty
                        <li class="text-gray-500">No logins yet.</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}" class="isp-quick-pill">Full activity log →</a>
    </div>
</x-filament-panels::page>
