@php
    /** @var \App\Models\MikrotikServer $record */
    $record = $this->getRecord();
    $status = (string) ($record->last_api_status ?: 'unknown');
    $badgeClass = match ($status) {
        'online' => 'net-rt-badge--online',
        'offline' => 'net-rt-badge--offline',
        default => 'net-rt-badge--unknown',
    };
    $listUrl = \App\Filament\Resources\MikrotikServerResource::getUrl('index');
    $liveUrl = \App\Filament\Pages\OnlineClientsMonitoring::getUrl();
@endphp

{!! \App\Support\NetworkStyles::navigatedScript() !!}

<x-filament-panels::page class="isp-network-noc-page">
    <div class="net-noc-pro space-y-4">
        <header class="net-profile-hero">
            <div style="display:flex;flex-wrap:wrap;align-items:flex-start;justify-content:space-between;gap:0.75rem;">
                <div>
                    <p class="net-profile-hero__eyebrow">Router profile</p>
                    <h1 class="net-profile-hero__title">{{ $record->name }}</h1>
                    <p class="net-profile-hero__sub">
                        {{ ($record->use_ssl ? 'ssl://' : '').$record->host.':'.$record->api_port }}
                        · MikroTik RouterOS
                    </p>
                </div>
                <span @class(['net-rt-badge', $badgeClass])>{{ $status }}</span>
            </div>

            <div class="net-profile-status">
                <div class="net-profile-status__item">
                    <span class="net-profile-status__label">API status</span>
                    <span class="net-profile-status__value">{{ $status }}</span>
                </div>
                <div class="net-profile-status__item">
                    <span class="net-profile-status__label">Enabled</span>
                    <span class="net-profile-status__value">{{ $record->is_enabled ? 'Yes' : 'No' }}</span>
                </div>
                <div class="net-profile-status__item">
                    <span class="net-profile-status__label">Subscribers</span>
                    <span class="net-profile-status__value">{{ number_format($record->customers()->count()) }}</span>
                </div>
                <div class="net-profile-status__item">
                    <span class="net-profile-status__label">Last check</span>
                    <span class="net-profile-status__value">{{ $record->last_checked_at?->diffForHumans() ?? '—' }}</span>
                </div>
            </div>

            <div class="net-profile-tabs" aria-label="Profile sections">
                <span class="net-profile-tab net-profile-tab--active">Overview</span>
                <span class="net-profile-tab">Router info</span>
                <span class="net-profile-tab">PPP defaults</span>
                <span class="net-profile-tab">API snapshot</span>
            </div>
        </header>

        <section class="net-profile-form isp-network-router-form">
            <x-filament-panels::form wire:submit.prevent="save" id="mikrotik-router-edit-form">
                {{ $this->form }}
                <div class="net-profile-form__actions">
                    <x-filament-panels::form.actions :actions="$this->getCachedFormActions()" :full-width="$this->hasFullWidthFormActions()" />
                </div>
            </x-filament-panels::form>
        </section>

        <div style="display:flex;flex-wrap:wrap;gap:0.5rem;">
            <a href="{{ $listUrl }}" class="net-rt-btn net-rt-btn--ghost" style="color:var(--net-text);border-color:var(--net-border);background:var(--net-card);">
                ← Routers list
            </a>
            <a href="{{ $liveUrl }}" class="net-rt-btn net-rt-btn--ghost" style="color:var(--net-text);border-color:var(--net-border);background:var(--net-card);">
                Live PPP sessions
            </a>
        </div>

        @if (filled($record->last_error))
            <p class="net-profile-notes">
                <strong>Last error</strong>
                {{ $record->last_error }}
            </p>
        @endif
    </div>
</x-filament-panels::page>
