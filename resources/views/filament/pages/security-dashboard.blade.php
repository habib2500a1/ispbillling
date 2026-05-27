@php
    $stats = $this->getSecurityStats();
    $statCards = [
        ['label' => 'Logins today', 'value' => (string) $stats['logins_today'], 'hint' => 'Successful staff sign-ins', 'class' => 'isp-hub-stat--sky'],
        ['label' => 'Failed today', 'value' => (string) $stats['failed_today'], 'hint' => 'Needs review', 'class' => 'isp-hub-stat--danger', 'valueClass' => 'isp-hub-stat-value--danger'],
        ['label' => 'Events today', 'value' => (string) $stats['activity_today'], 'hint' => 'Audit stream volume', 'class' => 'isp-hub-stat--rose'],
        ['label' => 'Staff', 'value' => (string) $stats['staff_total'], 'hint' => $stats['inactive_staff'].' inactive', 'class' => 'isp-hub-stat--slate'],
    ];
@endphp

<x-filament-panels::page>
    <div class="isp-hub-page space-y-6" wire:poll.30s>
        <x-isp.hub-hero
            eyebrow="Security operations"
            title="Security dashboard"
            description="Login attempts, failed logins, staff activity, and audit trail for tenant-wide access monitoring."
            class="isp-hub-hero--rose"
        >
            <div class="isp-hub-toolbar">
                <div class="isp-hub-toolbar__meta">
                    <span class="isp-hub-results">Audit feed live</span>
                    <span class="isp-hub-section__meta">{{ $stats['failed_today'] }} failed today</span>
                </div>
            </div>
        </x-isp.hub-hero>

        <x-isp.hub-stat-grid :stats="$statCards" />

        <div class="grid gap-4 lg:grid-cols-2">
            <section class="isp-ops-panel">
                <div class="isp-ops-panel__head">
                    <div>
                        <h3 class="isp-ops-panel__title">Recent failed logins</h3>
                        <p class="isp-ops-panel__desc">Latest rejected sign-in attempts to review for suspicious activity.</p>
                    </div>
                    <span class="isp-ops-pill isp-ops-pill--danger">Threats</span>
                </div>
                <div class="isp-ops-list">
                    @forelse ($stats['recent_failed'] as $row)
                        <div class="isp-ops-list__item">
                            <div class="isp-ops-list__primary">
                                <p class="isp-ops-list__title">{{ $row['ip_address'] ?? 'Unknown IP' }}</p>
                                <p class="isp-ops-list__meta">{{ $row['description'] ?? 'Failed login attempt' }}</p>
                            </div>
                            <span class="isp-ops-pill isp-ops-pill--danger">{{ \Carbon\Carbon::parse($row['created_at'])->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="isp-hub-empty">No failed attempts logged.</div>
                    @endforelse
                </div>
            </section>
            <section class="isp-ops-panel">
                <div class="isp-ops-panel__head">
                    <div>
                        <h3 class="isp-ops-panel__title">Recent sign-ins</h3>
                        <p class="isp-ops-panel__desc">Latest successful logins recorded in the tenant activity stream.</p>
                    </div>
                    <span class="isp-ops-pill isp-ops-pill--ok">Healthy</span>
                </div>
                <div class="isp-ops-list">
                    @forelse ($stats['recent_logins'] as $row)
                        <div class="isp-ops-list__item">
                            <div class="isp-ops-list__primary">
                                <p class="isp-ops-list__title">{{ $row['description'] }}</p>
                                <p class="isp-ops-list__meta">{{ $row['ip_address'] ?? 'IP unavailable' }}</p>
                            </div>
                            <span class="isp-ops-pill isp-ops-pill--ok">{{ \Carbon\Carbon::parse($row['created_at'])->diffForHumans() }}</span>
                        </div>
                    @empty
                        <div class="isp-hub-empty">No logins yet.</div>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="flex flex-wrap gap-2">
            <a href="{{ \App\Filament\Resources\ActivityLogResource::getUrl('index') }}" class="isp-quick-pill">Full activity log</a>
        </div>
    </div>
</x-filament-panels::page>
