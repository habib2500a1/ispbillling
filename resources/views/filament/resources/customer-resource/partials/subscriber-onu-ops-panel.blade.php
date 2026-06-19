@php
    $ops = $onuOps ?? [];
    $warning = $ops['warning'] ?? ['active' => false];
@endphp

@if (! empty($ops['linked']))
    <section class="isp-cv-card isp-cv-card--full sub-onu-ops" wire:poll.60s>
        <div class="isp-cv-card__head">
            <h3 class="isp-cv-card__title">ONU operations</h3>
            <span @class([
                'sub-onu-ops__status',
                'sub-onu-ops__status--' . ($ops['status_tone'] ?? 'gray'),
            ])>{{ $ops['status'] ?? '—' }}</span>
        </div>

        @if (! empty($warning['active']))
            <div @class([
                'sub-onu-ops__alert',
                'sub-onu-ops__alert--' . ($warning['level'] ?? 'warning'),
            ])>
                <strong>ONU alert</strong>
                <p>{{ $warning['message'] ?? 'ONU requires attention.' }}</p>
                @if (! empty($ops['ticket_suggest_url']))
                    <a href="{{ $ops['ticket_suggest_url'] }}" class="isp-cv-link text-sm">Create support ticket →</a>
                @endif
            </div>
        @endif

        <dl class="sub-onu-ops__grid">
            <div>
                <dt>RX power</dt>
                <dd @class(['sub-onu-ops__rx', 'sub-onu-ops__rx--' . ($ops['rx_level'] ?? 'unknown')])>
                    {{ $ops['rx_label'] ?? '—' }}
                    @if (! empty($ops['rx_level_label']))
                        <span class="text-xs opacity-80">{{ $ops['rx_level_label'] }}</span>
                    @endif
                </dd>
            </div>
            <div>
                <dt>TX power</dt>
                <dd class="font-mono">{{ $ops['tx_label'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Last seen</dt>
                <dd>{{ $ops['last_seen'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Uptime</dt>
                <dd>{{ $ops['uptime'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Reboot count</dt>
                <dd>{{ $ops['reboot_count'] ?? 0 }}</dd>
            </div>
            <div>
                <dt>Firmware</dt>
                <dd class="font-mono">{{ $ops['firmware'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>OLT</dt>
                <dd>{{ $ops['olt'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>PON</dt>
                <dd class="font-mono">{{ $ops['pon'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>ONU MAC</dt>
                <dd class="font-mono text-xs">{{ $ops['mac'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Temperature</dt>
                <dd>{{ $ops['temperature'] ?? '—' }}</dd>
            </div>
            <div>
                <dt>Distance</dt>
                <dd>{{ $ops['distance'] ?? '—' }}</dd>
            </div>
            @if (! empty($ops['offline_since']) && ($ops['offline_since'] ?? '—') !== '—')
                <div>
                    <dt>Offline since</dt>
                    <dd>{{ $ops['offline_since'] }}</dd>
                </div>
            @endif
        </dl>

        @if (! empty($ops['mac_archive']))
            <details class="sub-onu-ops__archive mt-3">
                <summary class="text-xs font-semibold text-slate-600 cursor-pointer">MAC archive ({{ count($ops['mac_archive']) }})</summary>
                <ul class="text-xs font-mono mt-2 space-y-1">
                    @foreach ($ops['mac_archive'] as $entry)
                        <li>{{ $entry['mac'] ?? '—' }} → {{ $entry['replaced_by'] ?? '—' }} · {{ $entry['archived_at'] ?? '' }}</li>
                    @endforeach
                </ul>
            </details>
        @endif
    </section>
@endif
