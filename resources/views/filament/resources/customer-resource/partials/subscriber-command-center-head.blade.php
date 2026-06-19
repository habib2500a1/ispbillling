@php
    $cc = $commandCenter ?? [];
    $strip = $cc['header_strip'] ?? [];
    $kpis = $cc['kpis'] ?? [];
    $fab = $cc['fab_actions'] ?? [];
@endphp

<div class="sub-cc-strip no-print" data-sub-sticky-strip>
    <div class="sub-cc-strip__badges">
        @if (! empty($strip['is_vip']))
            <span class="sub-cc-badge sub-cc-badge--vip">VIP</span>
        @endif
        @if (! empty($strip['is_corporate']))
            <span class="sub-cc-badge sub-cc-badge--corp">Corporate</span>
        @endif
        <span @class(['sub-cc-badge', $strip['online'] ?? false ? 'sub-cc-badge--online' : 'sub-cc-badge--offline'])>
            {{ ($strip['online'] ?? false) ? 'Online' : 'Offline' }} · {{ $strip['online_label'] ?? '—' }}
        </span>
        <span class="sub-cc-badge sub-cc-badge--due">Due {{ number_format((float) ($strip['due_bdt'] ?? 0), 0) }} BDT</span>
        <span class="sub-cc-badge sub-cc-badge--tickets">{{ (int) ($strip['open_tickets'] ?? 0) }} open tickets</span>
        <span class="sub-cc-badge sub-cc-badge--assignee">Assignee: {{ $strip['assignee'] ?? 'Unassigned' }}</span>
    </div>
</div>

@if ($kpis !== [])
    <div class="olt-stats sub-cc-kpis">
        @foreach ($kpis as $kpi)
            <div class="olt-stat sub-stat olt-stat--{{ $kpi['tone'] ?? 'gray' }}">
                <div class="olt-stat__row">
                    <span class="olt-stat__icon"><x-filament::icon :icon="$kpi['icon']" class="h-5 w-5" /></span>
                </div>
                <span class="olt-stat__label">{{ $kpi['label'] }}</span>
                <strong @class(['olt-stat__value', 'olt-stat__value--sm' => strlen((string) $kpi['value']) > 16])>{{ $kpi['value'] }}</strong>
                <span class="olt-stat__hint">{{ $kpi['meta'] }}</span>
            </div>
        @endforeach
    </div>
@endif

<div class="sub-cc-fab no-print" x-data="{ open: false }">
    <button type="button" class="sub-cc-fab__trigger" @click="open = !open" aria-label="Quick actions">
        <x-filament::icon icon="heroicon-o-bolt" class="h-6 w-6" />
    </button>
    <div class="sub-cc-fab__menu" x-show="open" x-cloak @click.outside="open = false">
        @foreach ($fab as $action)
            @if (($action['type'] ?? '') === 'wire')
                <button type="button" class="sub-cc-fab__item" wire:click="{{ $action['action'] }}" wire:loading.attr="disabled" @click="open = false">
                    <x-filament::icon :icon="$action['icon']" class="h-4 w-4" />{{ $action['label'] }}
                </button>
            @elseif (! empty($action['disabled']))
                <span class="sub-cc-fab__item sub-cc-fab__item--disabled"><x-filament::icon :icon="$action['icon']" class="h-4 w-4" />{{ $action['label'] }}</span>
            @else
                <a href="{{ $action['url'] }}" class="sub-cc-fab__item" @if (($action['type'] ?? '') === 'external') target="_blank" rel="noopener" @endif @click="open = false">
                    <x-filament::icon :icon="$action['icon']" class="h-4 w-4" />{{ $action['label'] }}
                </a>
            @endif
        @endforeach
    </div>
</div>
